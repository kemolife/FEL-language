#include "fel_runtime.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>

/* ── GC state ── */
static FelVal  *gc_heap          = NULL;   /* head of all allocated FelVal */
static FelVal **gc_root_stack[4096];       /* shadow stack of root slots */
static int      gc_root_sp       = 0;
static size_t   gc_alloc_count   = 0;
static const size_t GC_THRESHOLD = 512;

/* Allocate and track a new FelVal. Triggers collection when threshold hit. */
static FelVal *gc_new_val(void) {
    if (gc_alloc_count >= GC_THRESHOLD) {
        fel_gc_collect();
    }
    FelVal *v = malloc(sizeof(FelVal));
    if (!v) { fprintf(stderr, "fel: out of memory\n"); exit(1); }
    v->gc_marked = 0;
    v->gc_next   = gc_heap;
    gc_heap      = v;
    gc_alloc_count++;
    return v;
}

/* Mark a value and all values reachable from it. */
static void gc_mark(FelVal *v) {
    if (!v || v->gc_marked) return;
    v->gc_marked = 1;
    if (v->tag == FEL_ARRAY) {
        FelArr *arr = (FelArr *)(uintptr_t)v->payload;
        if (arr) {
            for (int64_t i = 0; i < arr->len; i++) {
                gc_mark(arr->data[i]);
            }
        }
    }
    /* FEL_STRING payload is FelStr* — not a FelVal, no marking needed */
    /* FEL_FUNCTION: when closures are added, mark captured env here */
}

/* Sweep: free all unmarked FelVal and associated heap data. */
static void gc_sweep(void) {
    FelVal **cursor = &gc_heap;
    while (*cursor) {
        FelVal *v = *cursor;
        if (!v->gc_marked) {
            *cursor = v->gc_next;           /* unlink */
            if (v->tag == FEL_STRING) {
                FelStr *s = (FelStr *)(uintptr_t)v->payload;
                if (s) { free(s->data); free(s); }
            } else if (v->tag == FEL_ARRAY) {
                FelArr *arr = (FelArr *)(uintptr_t)v->payload;
                if (arr) { free(arr->data); free(arr); }
            }
            free(v);
        } else {
            v->gc_marked = 0;               /* reset for next cycle */
            cursor = &v->gc_next;
        }
    }
}

/* ── Memory ── */
void *fel_alloc(int64_t size) {
    void *p = malloc((size_t)size);
    if (!p) { fprintf(stderr, "fel: out of memory\n"); exit(1); }
    return p;
}

void fel_free(void *ptr) { free(ptr); }

/* ── Constructors ── */
FelVal *fel_val_int(int64_t v) {
    FelVal *val   = gc_new_val();
    val->tag      = FEL_INT;
    val->payload  = v;
    return val;
}

FelVal *fel_val_float(double v) {
    FelVal *val = gc_new_val();
    val->tag    = FEL_FLOAT;
    memcpy(&val->payload, &v, sizeof(double));
    return val;
}

FelVal *fel_val_bool(int8_t v) {
    FelVal *val  = gc_new_val();
    val->tag     = FEL_BOOL;
    val->payload = v ? 1 : 0;
    return val;
}

FelVal *fel_val_null(void) {
    FelVal *val  = gc_new_val();
    val->tag     = FEL_NULL;
    val->payload = 0;
    return val;
}

/* ── String ── */
FelVal *fel_str_new(const char *data, int64_t len) {
    FelStr *s  = malloc(sizeof(FelStr));  /* FelStr tracked via FelVal */
    s->len     = len;
    s->data    = malloc((size_t)len + 1);
    memcpy(s->data, data, (size_t)len);
    s->data[len] = '\0';
    FelVal *val  = gc_new_val();
    val->tag     = FEL_STRING;
    val->payload = (int64_t)(uintptr_t)s;
    return val;
}

/* ── Array ── */
FelArr *fel_arr_new(int64_t capacity) {
    FelArr *arr = fel_alloc(sizeof(FelArr));
    arr->len  = 0;
    arr->cap  = capacity > 0 ? capacity : 4;
    arr->data = fel_alloc(arr->cap * sizeof(FelVal *));
    return arr;
}

void fel_arr_push(FelArr *arr, FelVal *val) {
    if (arr->len >= arr->cap) {
        arr->cap *= 2;
        FelVal **tmp = realloc(arr->data, arr->cap * sizeof(FelVal *));
        if (!tmp) { fprintf(stderr, "fel: out of memory\n"); exit(1); }
        arr->data = tmp;
    }
    arr->data[arr->len++] = val;
}

/* ── Operations ── */

static double fel_to_float(FelVal *v) {
    if (v->tag == FEL_INT)   return (double)v->payload;
    if (v->tag == FEL_FLOAT) { double d; memcpy(&d, &v->payload, 8); return d; }
    return 0.0;
}

FelVal *fel_binop(int8_t op, FelVal *left, FelVal *right) {
    /* String operations (both strings): concat and equality */
    if (left->tag == FEL_STRING && right->tag == FEL_STRING) {
        FelStr *ls = (FelStr *)(uintptr_t)left->payload;
        FelStr *rs = (FelStr *)(uintptr_t)right->payload;
        switch (op) {
            case 1: { /* concatenation */
                int64_t n = ls->len + rs->len;
                char *buf = malloc((size_t)n + 1);
                memcpy(buf, ls->data, (size_t)ls->len);
                memcpy(buf + ls->len, rs->data, (size_t)rs->len);
                buf[n] = '\0';
                FelVal *v = fel_str_new(buf, n);
                free(buf);
                return v;
            }
            case 6: /* == */
                return fel_val_bool(ls->len == rs->len &&
                                    memcmp(ls->data, rs->data, (size_t)ls->len) == 0);
            case 7: /* != */
                return fel_val_bool(!(ls->len == rs->len &&
                                      memcmp(ls->data, rs->data, (size_t)ls->len) == 0));
        }
        return fel_val_null();
    }
    /* Integer arithmetic (both int) */
    if (left->tag == FEL_INT && right->tag == FEL_INT) {
        int64_t l = left->payload, r = right->payload;
        switch (op) {
            case 1:  return fel_val_int(l + r);
            case 2:  return fel_val_int(l - r);
            case 3:  return fel_val_int(l * r);
            case 4:  return r ? fel_val_int(l / r) : fel_val_null();
            case 5:  return r ? fel_val_int(l % r) : fel_val_null();
            case 6:  return fel_val_bool(l == r);
            case 7:  return fel_val_bool(l != r);
            case 8:  return fel_val_bool(l < r);
            case 9:  return fel_val_bool(l > r);
            case 10: return fel_val_bool(l <= r);
            case 11: return fel_val_bool(l >= r);
            case 12: return fel_val_bool(fel_is_truthy(left) && fel_is_truthy(right));
            case 13: return fel_val_bool(fel_is_truthy(left) || fel_is_truthy(right));
        }
    }
    /* Float arithmetic */
    if (left->tag == FEL_INT || left->tag == FEL_FLOAT ||
        right->tag == FEL_INT || right->tag == FEL_FLOAT) {
        double l = fel_to_float(left), r = fel_to_float(right);
        switch (op) {
            case 1:  return fel_val_float(l + r);
            case 2:  return fel_val_float(l - r);
            case 3:  return fel_val_float(l * r);
            case 4:  return fel_val_float(r != 0.0 ? l / r : 0.0);
            case 5:  return fel_val_float(fmod(l, r));
            case 6:  return fel_val_bool(l == r);
            case 7:  return fel_val_bool(l != r);
            case 8:  return fel_val_bool(l < r);
            case 9:  return fel_val_bool(l > r);
            case 10: return fel_val_bool(l <= r);
            case 11: return fel_val_bool(l >= r);
            case 12: return fel_val_bool(fel_is_truthy(left) && fel_is_truthy(right));
            case 13: return fel_val_bool(fel_is_truthy(left) || fel_is_truthy(right));
        }
    }
    return fel_val_null();
}

FelVal *fel_unop(int8_t op, FelVal *val) {
    if (op == 1) { /* negation */
        if (val->tag == FEL_INT)   return fel_val_int(-val->payload);
        if (val->tag == FEL_FLOAT) return fel_val_float(-fel_to_float(val));
    }
    if (op == 0) { /* ! */
        return fel_val_bool(!fel_is_truthy(val));
    }
    return fel_val_null();
}

int8_t fel_is_truthy(FelVal *val) {
    if (!val || val->tag == FEL_NULL)  return 0;
    if (val->tag == FEL_BOOL)          return val->payload != 0;
    return 1;
}

/* ── Display ── */
void fel_display(FelVal *val) {
    if (!val) { puts("null"); return; }
    switch (val->tag) {
        case FEL_NULL:   puts("null");                              break;
        case FEL_INT:    printf("%ld\n", (long)val->payload);       break;
        case FEL_FLOAT:  { double d; memcpy(&d, &val->payload, 8); printf("%g\n", d); } break;
        case FEL_BOOL:   puts(val->payload ? "true" : "false");     break;
        case FEL_STRING: {
            FelStr *s = (FelStr *)(uintptr_t)val->payload;
            printf("%.*s\n", (int)s->len, s->data);
        } break;
        case FEL_ARRAY:  puts("[array]");                           break;
        default:         puts("[object]");                          break;
    }
}

/* ── GC ── */
void fel_gc_init(void) {
    gc_heap        = NULL;
    gc_root_sp     = 0;
    gc_alloc_count = 0;
}

void fel_gc_shutdown(void) {
    /* Collect everything — roots are gone by shutdown time */
    gc_root_sp = 0;
    fel_gc_collect();
}

void fel_gc_collect(void) {
    for (int i = 0; i < gc_root_sp; i++) {
        if (gc_root_stack[i] && *gc_root_stack[i]) {
            gc_mark(*gc_root_stack[i]);
        }
    }
    gc_sweep();
    gc_alloc_count = 0;
}

void fel_gc_push_root(FelVal **slot) {
    if (gc_root_sp >= 4096) {
        fprintf(stderr, "fel: GC root stack overflow — call depth too deep\n");
        return;
    }
    gc_root_stack[gc_root_sp++] = slot;
}

void fel_gc_pop_roots(int n) {
    gc_root_sp -= n;
    if (gc_root_sp < 0) gc_root_sp = 0;
}

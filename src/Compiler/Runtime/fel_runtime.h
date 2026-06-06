#ifndef FEL_RUNTIME_H
#define FEL_RUNTIME_H

#include <stdint.h>

/* Value tags */
#define FEL_NULL     0
#define FEL_INT      1
#define FEL_FLOAT    2
#define FEL_BOOL     3
#define FEL_STRING   4
#define FEL_ARRAY    5
#define FEL_HASH     6
#define FEL_FUNCTION 7

typedef struct FelVal {
    uint8_t   tag;
    uint8_t   gc_marked;       /* GC mark bit — was padding[0] */
    uint8_t   _pad[6];
    int64_t   payload;
    struct FelVal *gc_next;    /* intrusive list of all heap objects */
} FelVal;

typedef struct FelStr {
    int64_t  len;
    char    *data;
} FelStr;

typedef struct FelArr {
    int64_t   len;
    int64_t   cap;
    FelVal  **data;
} FelArr;

/* Constructors */
FelVal *fel_val_int(int64_t v);
FelVal *fel_val_float(double v);
FelVal *fel_val_bool(int8_t v);
FelVal *fel_val_null(void);

/* String */
FelVal *fel_str_new(const char *data, int64_t len);

/* Array */
FelArr *fel_arr_new(int64_t capacity);
void    fel_arr_push(FelArr *arr, FelVal *val);

/* Operations */
FelVal *fel_binop(int8_t op, FelVal *left, FelVal *right);
FelVal *fel_unop(int8_t op, FelVal *val);
int8_t  fel_is_truthy(FelVal *val);

/* I/O */
void    fel_display(FelVal *val);

/* GC */
void    fel_gc_init(void);
void    fel_gc_shutdown(void);
void    fel_gc_collect(void);
void    fel_gc_push_root(FelVal **slot);   /* register a GC root slot */
void    fel_gc_pop_roots(int n);           /* unregister last n roots */
void   *fel_alloc(int64_t size);
void    fel_free(void *ptr);

#endif /* FEL_RUNTIME_H */

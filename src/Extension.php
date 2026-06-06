<?php
declare(strict_types=1);
namespace Fel;

interface Extension {
    public function register(Engine $engine): void;
}

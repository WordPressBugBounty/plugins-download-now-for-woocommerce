<?php

/*
 * This file is part of Free Downloads.
 *
 * Copyright (c) Richard Webster
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace SOM\FreeDownloads;

final class CheckedProducts
{
    private array $checked_products = [];

    private array $checked_products_guest = [];

    public function __construct() {}

    public function productChecked(int $product_id): bool
    {
        return array_key_exists($product_id, $this->checked_products);
    }

    public function productCheckedGuest(int $product_id): bool
    {
        return array_key_exists($product_id, $this->checked_products_guest);
    }

    public function getProductChecked(int $product_id): bool
    {
        return array_key_exists($product_id, $this->checked_products)
        ? $this->checked_products[$product_id]
        : false;
    }

    public function getProductCheckedGuest(int $product_id): bool
    {
        return array_key_exists($product_id, $this->checked_products_guest)
        ? $this->checked_products_guest[$product_id]
        : false;
    }

    public function addProduct(int $product_id, bool $valid): void
    {
        $this->checked_products[$product_id] = $valid;
    }

    public function addProductGuest(int $product_id, bool $valid): void
    {
        $this->checked_products_guest[$product_id] = $valid;
    }
}

<?php
    include_once __DIR__ . "/user_fields.php";
    class ProductFields{
        public const ALL = [
             "pro.pro_id",
             "pro.evn_id",
             "pro.dep_id",
             "pro.procat_id",
             "pro.pro_name",
             "pro.pro_description",
             "pro.pro_price",
             "pro.pro_type",
             "pro.pro_image_url",
             "pro.pro_created_at",
             "pro.pro_updated_at"
        ];

        public const ALL_EXTRA_INFO = [
            ...self::ALL,
            "dep.dep_name",
            "dep.dep_value",
            "procat.procat_name"
        ];

        public const PRODUCT_PER_LOCATION = [
            ...self::ALL_EXTRA_INFO,
            "proloc.proloc_id",
            "proloc.proloc_name"
        ];

        public const PRODUCT_ORDER = [
            ...self::ALL_EXTRA_INFO,
            "ordpro.ordpro_amount"
        ];

        public const PRODUCT_WITH_USER = [
            ...UserFields::DISPLAY_USER,
            "pro.pro_id",
            "pro.pro_name",
            "pro.pro_price",
            "ordpro.ordpro_amount"
        ];

        public const SUMMARY_ORDER = [
            "pro.pro_name",
            "pro.pro_price",
            "SUM(ordpro.ordpro_amount) AS pro_total_amount",
            "SUM(pro.pro_price * ordpro.ordpro_amount) AS pro_total_price"
        ];
    }
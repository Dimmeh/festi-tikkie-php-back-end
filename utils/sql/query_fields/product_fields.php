<?php
    class ProductFields{
        public const ALL = [
             "pro_id",
             "evn_id",
             "dep_id",
             "procat_id",
             "pro_name",
             "pro_description",
             "pro_price",
             "pro_type",
             "pro_image_url",
             "pro_created_at",
             "pro_updated_at"
        ];

        public const ALL_EXTRA_INFO = [
            "p.pro_id",
            "p.evn_id",
            "p.dep_id",
            "p.procat_id",
            "p.pro_name",
            "p.pro_description",
            "p.pro_price",
            "p.pro_type",
            "p.pro_image_url",
            "p.pro_created_at",
            "p.pro_updated_at",
            "d.dep_name",
            "d.dep_value",
            "c.procat_name"
        ];

        public const PRODUCT_PER_LOCATION = [
            ...self::ALL_EXTRA_INFO,
            "l.proloc_id",
            "l.proloc_name"
        ];

        public const PRODUCT_ORDER = [
            ...self::ALL_EXTRA_INFO,
            "o.ordpro_amount"
        ];
    }
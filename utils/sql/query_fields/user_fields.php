<?php 

    class UserFields{
        public const ALL = [
            "usr_id",
            "usr_name",
            "usr_password_hash",
            "usr_email",
            "usr_profile_photo_url",
            "usr_code",
            "usr_created_at",
            "usr_updated_at"
        ];
        public const ALL_WITHOUT_PASSWORD = [
            "usr_id",
            "usr_name",
            "usr_email",
            "usr_profile_photo_url",
            "usr_code",
            "usr_created_at",
            "usr_updated_at"
        ];
    }
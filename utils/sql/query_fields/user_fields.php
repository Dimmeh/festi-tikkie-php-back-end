<?php 

    class UserFields{
        public const ALL = [
            "usr.usr_id",
            "usr.usr_name",
            "usr.usr_password_hash",
            "usr.usr_email",
            "usr.usr_profile_photo_url",
            "usr.usr_code",
            "usr.usr_created_at",
            "usr.usr_updated_at"
        ];
        public const ALL_WITHOUT_PASSWORD = [
            "usr.usr_id",
            "usr.usr_name",
            "usr.usr_email",
            "usr.usr_profile_photo_url",
            "usr.usr_code",
            "usr.usr_created_at",
            "usr.usr_updated_at"
        ];

        public const DISPLAY_USER = [
            "usr.usr_id",
            "usr.usr_name",
            "usr.usr_email",
            "usr.usr_profile_photo_url"
        ];
    }
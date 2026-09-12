<?php
class BalanceFields{
    public const ALL = [
        "deb.invrou_id",
        "deb.invrou_creator_id",
        "deb.invusr_id",
        "deb.deb_amount",
        "deb.deb_created_at",
        "deb.deb_updated_at",
    ];

    public const SUM_BY_INVITED_USER = [
        "deb.invusr_id AS usr_id",
        "SUM(deb.deb_amount) AS deb_amount",
    ];

    public const SUM_BY_CREATOR = [
        "deb.invrou_creator_id AS usr_id",
        "deb.invusr_id",
        "SUM(deb.deb_amount) AS deb_amount",
    ];
}
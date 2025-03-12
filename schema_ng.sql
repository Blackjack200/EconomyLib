drop database xyron;

create database xyron;
use xyron;

create table account_metadata
(
    uid                bigint unsigned auto_increment primary key unique,
    xuid               varchar(128) unique             null,
    last_modified_time bigint unsigned                 not null,
    player_name        varchar(16)                     not null,
    created_at         timestamp default current_timestamp() not null,
    data               json      default json_object() not null,
    constraint account
        check (`xuid` is not null or `player_name` is not null)
) charset = utf8;

create table rank_registry
(
    basename varchar(128) unique not null,
    display  varchar(256)        not null,
    primary key (basename)
) default charset = utf8;

create table rank_player_data
(
    uid bigint unsigned,
    xuid     varchar(128)    not null,
    basename varchar(128)    not null,
    deadline bigint unsigned not null,
    foreign key (uid) references account_metadata (uid)
        on delete cascade,
    foreign key (xuid) references account_metadata (xuid)
        on delete cascade,
    foreign key (basename) references rank_registry (basename)
        on delete cascade,
    primary key (uid, basename)
) default charset = utf8;

create table statistics_data
(
    id   BIGINT unsigned unique not null auto_increment,
    type varchar(128),
    data json,
    primary key (id)
) default charset = utf8;

create table statistics_player_data
(
    xuid varchar(128)    not null,
    id   BIGINT unsigned not null,
    foreign key (xuid) references account_metadata (xuid)
        on delete cascade,
    foreign key (id) references statistics_data (id)
        on delete cascade,
    primary key (xuid, id)
) default charset = utf8;

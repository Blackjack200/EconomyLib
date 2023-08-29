create database `xyron`;
use `xyron`;

create table `player_xuid`
(
    `xuid`        varchar(128) unique not null,
    `player_name` varchar(16) unique  not null,
    primary key (`xuid`)
) default charset = utf8;

create table `player_account`
(
    `xuid`  varchar(128) unique not null,
    `kill`  integer unsigned    not null default 0,
    `death` integer unsigned    not null default 0,
    foreign key (`xuid`) references player_xuid (xuid),
    primary key (`xuid`)
) default charset = utf8;

create table `rank_registry`
(
    `basename` varchar(128) unique not null,
    `display`  varchar(256)        not null,
    primary key (`basename`)
) default charset = utf8;

create table rank_player_data
(
    xuid     varchar(128) unique not null,
    basename varchar(128) unique not null,
    has      boolean,
    foreign key (xuid) references player_xuid (xuid),
    foreign key (basename) references rank_registry (basename),
    primary key (xuid, basename)
) default charset = utf8;

drop table rank_player_data;
drop table rank_registry;
drop table player_account;
drop table player_xuid;

#Register an account (xuid,name)
insert player_xuid (xuid, player_name)
values ('2525bf2d-45c0-11ee-948e-fa3c1c5507af', 'IPlayfordev');
insert into player_account (xuid, `kill`, death)
values ('2525bf2d-45c0-11ee-948e-fa3c1c5507af', 1000, 10);

insert player_xuid (xuid, player_name)
values ('53a84f74-cd21-4ae2-b783-48ef700fa379', 'TwoPandora94601');
insert into player_account (xuid, `kill`, death)
values ('53a84f74-cd21-4ae2-b783-48ef700fa379', 1000, 10);

#get an account's data (name)
select *
from player_account
where xuid = (select xuid from player_xuid where player_name = 'IPlayfordev');

#update an account's data (name)
update player_account
set `kill`=1
where xuid = (select xuid from player_xuid where player_name = 'IPlayfordev');

#delete an account (name)
delete
from player_account
where xuid = (select xuid from player_xuid where player_name = 'IPlayfordev');
delete
from rank_player_data
where xuid = (select xuid from player_xuid where player_name = 'IPlayfordev');

select *
from player_xuid;
select *
from player_account;
select *
from rank_registry;
select *
from rank_player_data;

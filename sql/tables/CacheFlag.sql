-- Used by the StoreCacheTableUpdater application
create table CacheFlag (
	shortname varchar(255),
	dirty boolean not null default false
);

create index CacheFlag_shortname_index on CacheFlag(shortname);

insert into CacheFlag (shortname, dirty) values
	('CategoryVisibleProductCountByRegion', false);

insert into CacheFlag (shortname, dirty) values
	('VisibleProduct', false);

insert into CacheFlag (shortname, dirty) values
	('CategoryVisibleItemCountByRegion', false);

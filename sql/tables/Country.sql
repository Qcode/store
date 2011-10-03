create table Country (
	-- this is the ISO-3611 two-letter country code for this country
	id char(2),
	title varchar(255),
	visible boolean not null default true,
	has_postal_code boolean not null default true,
	primary key(id)
);

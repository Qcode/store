create table Product (
	id serial,
	catalog int not null references Catalog(id) on delete cascade,
	title varchar(255) not null,
	bodytext text,
	createdate timestamp,
	shortname varchar(255),
	keywords varchar(255),
	reviewable boolean not null default true,
	primary key (id)
);

CREATE INDEX Product_catalog_index ON Product(catalog);

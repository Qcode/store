create table ProvState (
	id serial,
	country char(2) not null references Country(id) on delete cascade,
	title varchar(100),
	abbreviation varchar(10),
	tax_message varchar(500),
	primary key (id)
);

CREATE INDEX ProvState_country_index ON ProvState(country);
CREATE INDEX ProvState_abbreviation_index ON ProvState(abbreviation);

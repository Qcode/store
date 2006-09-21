-- renamed from customers
create table Account (
	id serial,
	fullname varchar(255),
	email varchar(255),
	phone varchar(100),
	password varchar(255),
	password_tag varchar(255),
	createdate timestamp,
	last_login timestamp,
	primary key (id)
);

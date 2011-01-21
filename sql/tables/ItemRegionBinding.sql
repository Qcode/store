create table ItemRegionBinding (
	item int not null references Item(id) on delete cascade,
	region int not null references Region(id),
	enabled boolean not null default true,
	price numeric(11, 2),
	original_price numeric(11, 2),
	sale_discount_price numeric(11, 2),
	primary key (item, region)
);

CREATE INDEX item_price_index ON ItemRegionBinding(price);
CREATE INDEX item_price_region_index ON ItemRegionBinding(price, region);

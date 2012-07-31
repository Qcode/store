create table RegionBillingProvStateBinding (
	region int not null references Region(id) on delete cascade,
	provstate int not null references ProvState(id) on delete cascade,
	primary key (region, provstate)
);

create index RegionBillingProvStateBinding_region on
	RegionBillingProvStateBinding(region);

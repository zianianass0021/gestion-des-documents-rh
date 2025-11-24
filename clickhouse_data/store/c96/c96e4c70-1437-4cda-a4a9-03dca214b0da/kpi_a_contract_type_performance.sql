ATTACH TABLE _ UUID '1773d1a7-514d-4746-9126-8ffe54375201'
(
    `event_date` Date,
    `contract_type` String,
    `personnel_completion` Float64,
    `ayant_droits_completion` Float64,
    `missing_docs_personnel` UInt32,
    `missing_docs_ayantdroits` UInt32
)
ENGINE = MergeTree
ORDER BY (event_date, contract_type)
SETTINGS index_granularity = 8192

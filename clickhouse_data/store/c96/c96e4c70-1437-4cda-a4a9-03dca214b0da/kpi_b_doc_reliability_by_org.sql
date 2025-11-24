ATTACH TABLE _ UUID '71a888f6-5cc5-4a38-84a3-d3c33948014e'
(
    `event_date` Date,
    `organization` String,
    `completion` Float64,
    `missing` UInt32
)
ENGINE = MergeTree
ORDER BY (event_date, organization)
SETTINGS index_granularity = 8192

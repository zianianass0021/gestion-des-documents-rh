ATTACH TABLE _ UUID '46ed78f8-dfdb-42f6-a144-96a7498ddd36'
(
    `event_date` Date,
    `dimension` String,
    `sub_dimension` String,
    `value` Float64
)
ENGINE = MergeTree
ORDER BY (event_date, dimension, sub_dimension)
SETTINGS index_granularity = 8192

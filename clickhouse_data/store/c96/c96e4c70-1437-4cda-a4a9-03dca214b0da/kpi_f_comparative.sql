ATTACH TABLE _ UUID 'f4a5170d-2c84-4088-ad06-a962ddc152cb'
(
    `event_date` Date,
    `segment` String,
    `metric` String,
    `value` Float64
)
ENGINE = MergeTree
ORDER BY (event_date, segment, metric)
SETTINGS index_granularity = 8192

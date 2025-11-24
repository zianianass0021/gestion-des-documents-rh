ATTACH TABLE _ UUID '71ca780f-06e3-4975-bbb8-58962fb9381f'
(
    `event_date` Date,
    `metric` String,
    `value` Float64
)
ENGINE = MergeTree
ORDER BY (event_date, metric)
SETTINGS index_granularity = 8192

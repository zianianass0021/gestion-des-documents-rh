ATTACH TABLE _ UUID '807a02da-2b5f-409e-978f-33e210ae2f1d'
(
    `event_date` Date,
    `document_name` String,
    `have` UInt32,
    `missing` UInt32
)
ENGINE = MergeTree
ORDER BY (event_date, document_name)
SETTINGS index_granularity = 8192

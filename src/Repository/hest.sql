SELECT w0_.worklog_id               AS worklog_id_0,
       w0_.is_billed                AS is_billed_1,
       w0_.description              AS description_2,
       w0_.worker                   AS worker_3,
       w0_.time_spent_seconds       AS time_spent_seconds_4,
       w0_.started                  AS started_5,
       w0_.billed_seconds           AS billed_seconds_6,
       w0_.project_tracker_issue_id AS project_tracker_issue_id_7,
       w0_.kind                     AS kind_8,
       w0_.id                       AS id_9,
       w0_.created_by               AS created_by_10,
       w0_.updated_by               AS updated_by_11,
       w0_.created_at               AS created_at_12,
       w0_.updated_at               AS updated_at_13,
       w0_.invoice_entry_id         AS invoice_entry_id_14,
       w0_.project_id               AS project_id_15,
       w0_.issue_id                 AS issue_id_16,
       w0_.data_provider_id         AS data_provider_id_17
FROM worklog w0_
         LEFT JOIN project p1_ ON (p1_.id = w0_.project_id)
         LEFT JOIN issue i2_ ON w0_.issue_id = i2_.id
         LEFT JOIN issue_epic i4_ ON i2_.id = i4_.issue_id
         LEFT JOIN epic e3_ ON e3_.id = i4_.epic_id
WHERE (w0_.started BETWEEN "2024-11-04 00:00:00.000000" AND "2024-11-10 23:59:59.000000")
  AND w0_.worker = ?
  AND p1_.is_billable = 1
  AND e3_.title NOT IN (?)

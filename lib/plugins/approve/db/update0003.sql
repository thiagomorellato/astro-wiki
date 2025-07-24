CREATE TABLE maintainer (
    id INTEGER PRIMARY KEY,
    namespace TEXT NOT NULL,
    approver TEXT NULL
);

CREATE TABLE revision (
    page TEXT NOT NULL,
    rev INTEGER NOT NULL,
    ready_for_approval TEXT NULL,
    ready_for_approval_by TEXT NULL,
    approved TEXT NULL,
    approved_by TEXT NULL,
    version INTEGER NULL,
    current INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (page, rev)
);

CREATE TABLE page (
    page TEXT PRIMARY KEY,
    approver TEXT NULL,
    hidden INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE config (
    key TEXT PRIMARY KEY,
    value TEXT NULL
);

CREATE INDEX idx_revision_current
    ON revision (current, page, rev, ready_for_approval, approved, version);

CREATE INDEX idx_page_maintainer
    ON page (approver, page, hidden);

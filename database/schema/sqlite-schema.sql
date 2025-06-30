CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "stripe_id" varchar,
  "pm_type" varchar,
  "pm_last_four" varchar,
  "trial_ends_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "videos"(
  "id" integer primary key autoincrement not null,
  "title" varchar not null,
  "description" text,
  "price" numeric not null default '0',
  "telegram_file_id" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "videos_telegram_file_id_index" on "videos"("telegram_file_id");
CREATE INDEX "videos_price_index" on "videos"("price");
CREATE UNIQUE INDEX "videos_telegram_file_id_unique" on "videos"(
  "telegram_file_id"
);
CREATE INDEX "users_stripe_id_index" on "users"("stripe_id");
CREATE TABLE IF NOT EXISTS "subscriptions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "type" varchar not null,
  "stripe_id" varchar not null,
  "stripe_status" varchar not null,
  "stripe_price" varchar,
  "quantity" integer,
  "trial_ends_at" datetime,
  "ends_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "subscriptions_user_id_stripe_status_index" on "subscriptions"(
  "user_id",
  "stripe_status"
);
CREATE UNIQUE INDEX "subscriptions_stripe_id_unique" on "subscriptions"(
  "stripe_id"
);
CREATE TABLE IF NOT EXISTS "subscription_items"(
  "id" integer primary key autoincrement not null,
  "subscription_id" integer not null,
  "stripe_id" varchar not null,
  "stripe_product" varchar not null,
  "stripe_price" varchar not null,
  "quantity" integer,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "subscription_items_subscription_id_stripe_price_index" on "subscription_items"(
  "subscription_id",
  "stripe_price"
);
CREATE UNIQUE INDEX "subscription_items_stripe_id_unique" on "subscription_items"(
  "stripe_id"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_06_30_023441_create_videos_table',2);
INSERT INTO migrations VALUES(5,'2025_06_30_025508_create_customer_columns',3);
INSERT INTO migrations VALUES(6,'2025_06_30_025509_create_subscriptions_table',3);
INSERT INTO migrations VALUES(7,'2025_06_30_025510_create_subscription_items_table',3);

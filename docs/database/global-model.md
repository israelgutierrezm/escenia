# Global Data Model — High Level

Este documento NO sustituye ERDs por bounded context.

## Identity / Tenancy

- users
- user_devices
- tenants
- tenant_memberships
- workspaces
- workspace_memberships

## Events

- events
- event_capabilities
- event_sessions
- event_tracks
- event_rooms
- event_speakers
- event_schedule_items

## Studio / Production

- studios
- studio_sessions
- studio_participants
- scenes
- scene_versions
- scene_elements
- brand_kits
- brand_assets
- run_of_show_items
- producer_cues

## Streaming

- media_rooms
- ingresses
- broadcast_sessions
- stream_destinations
- event_destinations
- stream_outputs
- stream_health_samples
- broadcast_incidents

## Registration / Audience

- registration_forms
- registration_fields
- registrations
- registration_answers
- contacts
- attendees
- attendee_sessions

## Engagement

- messages
- questions
- question_votes
- polls
- poll_options
- poll_answers
- resources
- resource_downloads
- ctas
- cta_interactions

## Commerce

- products
- offers
- coupons
- tickets
- orders
- order_items
- payments
- refunds

## Learning

- assessments
- assessment_questions
- attempts
- answers
- certificates
- certificate_rules

## Media / Content

- media_assets
- recording_sessions
- recording_tracks
- transcripts
- transcript_segments
- chapters
- clips
- edit_projects

## Automation

- workflows
- workflow_versions
- workflow_nodes
- workflow_edges
- workflow_executions
- workflow_execution_steps

## Analytics / Billing / Audit

- analytics_event_outbox
- metric_snapshots
- subscriptions
- entitlements
- usage_ledger
- audit_logs
- webhook_endpoints
- webhook_deliveries

La definición física debe evolucionar por fase. No crear todas estas tablas en Fase 0.

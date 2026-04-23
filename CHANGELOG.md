# Changelog

All notable changes to `Dashed Ecommerce Core` will be documented in this file.

## v4.5.4 - 2026-04-23

### Fixed

- Migration `add_concept_snapshot_to_orders`: `->after('prices_ex_vat')` verwijderd
  omdat `prices_ex_vat` pas in een latere migratie (`...000004`) wordt toegevoegd.
  De migratie faalde op fresh installs met "Column not found: prices_ex_vat".

## 1.0.0 - 202X-XX-XX

- initial release

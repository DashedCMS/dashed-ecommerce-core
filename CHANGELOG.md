# Changelog

All notable changes to `Dashed Ecommerce Core` will be documented in this file.

## v4.5.6 - 2026-04-27

### Added
- `DashedEcommerceCoreServiceProvider::bootingPackage()` registreert de eigen Filament navigatiegroepen via de nieuwe dashed-core registry: E-commerce (sort 30), Producten (40), Statistics (110), Export (120). Vereist dashed-core v4.2.0+.

## v4.5.5 - 2026-04-26

### Fixed
- Drie order-log-tags hadden geen beschrijving en toonden "ERROR tag niet gevonden" in de timeline:
  - `abandoned-cart.converted`: "heeft de bestelling alsnog afgerond na een verlaten-winkelmand-flow."
  - `order.system.cancelled.mail.send`: "heeft de annulerings mail automatisch verstuurd."
  - `order.system.cancelled.mail.send.failed`: "heeft de annulerings mail automatisch niet kunnen versturen vanwege een fout."

## v4.5.4 - 2026-04-23

### Fixed

- Migration `add_concept_snapshot_to_orders`: `->after('prices_ex_vat')` verwijderd
  omdat `prices_ex_vat` pas in een latere migratie (`...000004`) wordt toegevoegd.
  De migratie faalde op fresh installs met "Column not found: prices_ex_vat".

## 1.0.0 - 202X-XX-XX

- initial release

---
paths:
  - 'database/migrations/**'
---

# Migrations

## DDL MySQL fuera de DB::transaction
ALTER TABLE (add/drop unique, foreign key) hace commit implícito en MySQL. Si se envuelve un ALTER dentro de DB::transaction, la migración queda a medias (índice creado pero migración sin registrar en `migrations`). Ejecutar la fusión de datos (OdontogramConsolidator) en su propia transacción y el ALTER después, sin transacción externa.

## Nombres de índice: máximo 64 caracteres en MySQL
Los tests corren en SQLite, que no limita el largo de los identificadores; MySQL corta en 64 y la migración falla a mitad (los ALTER previos quedan aplicados). En `unique()`/`index()` sobre tablas con nombres largos (p. ej. `odontogram_tooth_findings`), pasar un nombre explícito corto como segundo argumento.

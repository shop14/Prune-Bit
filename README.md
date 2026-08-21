# PruneBit — Non-Custodial Edition

PruneBit is a multi-coin cryptocurrency wallet backend + frontend supporting
Bitcoin, Ethereum, Litecoin, Dogecoin and more.

**This edition is fully non-custodial.** The server never stores seed phrases,
private keys, or recoverable copies of your PIN. It stores only:

- a bcrypt hash of your wallet PIN (for unlocking),
- the derived public addresses per coin (for balances/history).

## What is intentionally NOT possible in this edition

Because no keys exist server-side, the following features are removed:

| Feature | Status |
|---|---|
| Create / import wallet (BIP39, xprv, WIF, hex) | ✅ Works — addresses derived, keys discarded |
| Balances & transaction history | ✅ Works (read-only explorer APIs) |
| Receive addresses & QR codes | ✅ Works |
| **Send / sign transactions** | ❌ Requires client-side signing (roadmap) |
| Show mnemonic / export private key | ❌ Impossible by design |
| Encrypted backups export | ❌ Requires stored seed |

The low-level builders in `classes/TransactionBuilder.php` still accept an
explicit private key, so client-side signing can be layered on without
reintroducing server-side key storage.

## Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `gmp`, `mbstring`, `json`, `curl`
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` (or any server that routes requests to `index.php`)

## Setup

1. Clone and point your web root at this folder (or deploy behind any PHP host).

2. Create the database schema:

   ```sql
   CREATE DATABASE prunebit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   mysql -u USER -p prunebit < install.sql
   ```

   (Most auxiliary tables are also created automatically on first use.)

3. Configure environment. Either set real environment variables or create
   `secret/.env` from `.env.example`:

   ```bash
   mkdir -p secret && cp .env.example secret/.env
   # edit secret/.env with your DB credentials
   ```

4. Optional API keys: copy `config/api_keys.example.php` to
   `config/api_keys.php` and fill in values. Everything degrades gracefully
   when keys are empty.

5. Ensure `secret/` is not web-accessible. A root `.htaccess` blocking it is
   included; verify by requesting `/secret/.env` (must return 403/404).

## Security model

- Seed phrases exist only in memory during create/import, then are discarded.
- PINs are hashed with bcrypt (cost 12) and never stored in recoverable form.
- Sessions are opaque random tokens bound to a wallet id.
- Captcha protects setup/import/unlock flows; banned-IP list included.
- CSRF origin checks on all non-GET API calls.

## Author

**Prune Bit** — Founder & project lead.

## License

MIT — see [LICENSE](LICENSE). © Prune Bit

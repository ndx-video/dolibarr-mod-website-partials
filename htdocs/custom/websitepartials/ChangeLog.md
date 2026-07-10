# CHANGELOG — websitepartials for [Dolibarr ERP CRM](https://www.dolibarr.org)

## development

- Public islands: `public/partial.php` serves published `.html` / `.json` (IP allowlist, Cache-Control, no PHP execution); `websitepartials_page_to_public_array()`.
- Admin setup page (`admin/setup.php`): public IP/CIDR allowlist, default website ref, Cache-Control, consumer URL jump list; documents global `API_RESTRICT_ON_IP` for REST.
- Lib helpers: `websitepartials_ip_allowed` (CIDR), getters for setup consts.
- Full REST CRUD for websites and all container types; DELETE supported.
- Module-owned nested permissions: `websitepartials/{website|page|blogpost|…}/{read|write|delete}`.
- Shared lib includes Website file helpers (`files` / `website` / `website2`) required by core create/delete.
- Earlier: status, list, get, create/update pages; descriptor scaffold (P0).

# Block View Source / Inspect

Simple guide for the **inspect blocking** feature in **PBN Hidden Link Manager** (v1.3.0+).

---

## What it does

When **ON**, the plugin loads a small front-end script that blocks common ways visitors open View Source / DevTools:

- Right-click (context menu)
- Ctrl+U / Cmd+U (view source)
- F12
- Ctrl+Shift+I / J / C
- Cmd+Option+I / J / C (Mac)

When **OFF**, the site behaves normally (inspect allowed).

**Note:** This is a light browser deterrent only. It does not fully stop advanced users, bots, or tools like curl.

---

## Turn ON / OFF in WordPress

1. Go to **WP Admin → PBN Hidden Links → Settings**
2. Find **Block view source / inspect**
3. Toggle ON or OFF
4. Click **Save settings**

Default for new installs: **OFF**

---

## Turn ON / OFF from dashboard (API)

**Base URL:**

```text
https://yoursite.com/wp-json/pbn-hidden-link-manager/v1
```

**Auth (either header):**

```http
Authorization: Bearer YOUR_API_KEY
```

or

```http
X-API-Key: YOUR_API_KEY
```

### Enable blocking

```http
POST /hidden-links/toggle-inspect
Content-Type: application/json

{
  "block_inspect": true
}
```

**Full URL example:**

```text
https://yoursite.com/wp-json/pbn-hidden-link-manager/v1/hidden-links/toggle-inspect
```

**Success response:**

```json
{
  "status": true,
  "block_inspect": true,
  "message": "Inspect / view-source blocking enabled."
}
```

### Disable blocking

```json
{
  "block_inspect": false
}
```

**Success response:**

```json
{
  "status": true,
  "block_inspect": false,
  "message": "Inspect / view-source blocking disabled."
}
```

---

## Check current state

```http
GET /status
```

**Example response:**

```json
{
  "status": true,
  "message": "API is operational.",
  "block_inspect": false,
  "show_hidden_links": true
}
```

Use `block_inspect` to sync the toggle in your dashboard UI.

---

## Postman body (no escapes)

**ON:**

```json
{
  "block_inspect": true
}
```

**OFF:**

```json
{
  "block_inspect": false
}
```

---

## Related files in plugin

| File | Role |
|------|------|
| `assets/block-inspect.js` | Front-end block script |
| `includes/class-shortcode.php` | Enqueues script when option is ON |
| `includes/class-rest-controller.php` | `POST /hidden-links/toggle-inspect` + status fields |
| Settings UI | WP Admin toggle |

---

## Quick checklist for dashboard

- [ ] Call `GET /status` → read `block_inspect`
- [ ] UI switch → `POST /hidden-links/toggle-inspect` with `{ "block_inspect": true|false }`
- [ ] Prefer HTTPS API URL when the site has SSL

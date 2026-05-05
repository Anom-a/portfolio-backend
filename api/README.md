# Portfolio Contact API

Framework-free PHP 8.1 API for the portfolio contact form and JWT-protected admin panel.

## Base URL

Local development:

```text
http://localhost:8080
```

Production:

```text
https://api.anomabebe.tech
```

All request and response bodies are JSON unless noted otherwise.

## Headers

For JSON requests:

```http
Content-Type: application/json
```

For protected admin routes:

```http
Authorization: Bearer <JWT>
```

## Public Endpoints

### `GET /api/health`

Health check endpoint for uptime monitors. Does not require DB access or authentication.

Response `200`:

```json
{
  "status": "ok",
  "timestamp": "2026-05-05T16:47:25+03:00"
}
```

### `POST /api/contact`

Creates a contact message, stores it in the database, and sends an admin email notification through Resend.

Rate limit: maximum 3 requests per IP per hour.

Request:

```json
{
  "name": "Test User",
  "email": "test@example.com",
  "subject": "Project inquiry",
  "message": "Hello, I would like to talk about a project."
}
```

Validation:

```text
name     required
email    required, valid email
subject  required
message  required, 10-500 characters
```

Success response `201`:

```json
{
  "success": true,
  "message": "Message sent"
}
```

Validation error `422`:

```json
{
  "success": false,
  "errors": {
    "email": "Invalid email address",
    "message": "Must be between 10 and 500 characters"
  }
}
```

Rate limit response `429`:

```json
{
  "success": false,
  "message": "Too many requests"
}
```

Server error `500`:

```json
{
  "success": false,
  "message": "Unable to send message"
}
```

## Auth Endpoints

### `POST /api/admin/login`

Authenticates an admin and returns a JWT valid for 24 hours.

Request:

```json
{
  "email": "admin@anomabebe.tech",
  "password": "admin-password"
}
```

Success response `200`:

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_in": 86400
}
```

Invalid credentials `401`:

```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

Server error `500`:

```json
{
  "success": false,
  "message": "Server error"
}
```

### `POST /api/admin/logout`
•••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••
Protected. Stateless JWT logout endpoint. Token invalidation is handled client-side.

Headers:

```http
Authorization: Bearer <JWT>
```

Success response `200`:

```json
{
  "success": true
}
```

Unauthorized `401`:

```json
{
  "success": false,
  "message": "Unauthorized"
}
```

## Admin Message Endpoints

All routes in this section require:

```http
Authorization: Bearer <JWT>
```

### `GET /api/admin/messages`

Lists contact messages with pagination, read-status filtering, and search.

Query params:

```text
page    optional, default 1
limit   optional, default 20, max 100
status  optional, all|unread|read, default all
search  optional, searches name, email, subject
```

Example:

```text
GET /api/admin/messages?page=1&limit=20&status=unread&search=test
```

Response `200`:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Test User",
      "email": "test@example.com",
      "subject": "Project inquiry",
      "message": "Hello, I would like to talk about a project.",
      "is_read": 0,
      "created_at": "2026-05-05T16:47:25+03:00"
    }
  ],
  "pagination": {
    "total": 1,
    "page": 1,
    "limit": 20,
    "total_pages": 1
  }
}
```

Response header:

```http
X-Total-Count: 1
```

Unauthorized `401`:

```json
{
  "success": false,
  "message": "Unauthorized"
}
```

Server error `500`:

```json
{
  "success": false,
  "message": "Server error"
}
```

### `GET /api/admin/messages/{id}`

Returns one message. If the message is unread, it is marked as read before returning.

Example:

```text
GET /api/admin/messages/1
```

Response `200`:

```json
{
  "id": 1,
  "name": "Test User",
  "email": "test@example.com",
  "subject": "Project inquiry",
  "message": "Hello, I would like to talk about a project.",
  "is_read": 1,
  "created_at": "2026-05-05T16:47:25+03:00"
}
```

Not found `404`:

```json
{
  "success": false,
  "message": "Not found"
}
```

### `PATCH /api/admin/messages/{id}/read`

Updates a message read status.

Request:

```json
{
  "is_read": 1
}
```

Accepted values:

```text
0 unread
1 read
```

Response `200`:

```json
{
  "id": 1,
  "name": "Test User",
  "email": "test@example.com",
  "subject": "Project inquiry",
  "message": "Hello, I would like to talk about a project.",
  "is_read": 1,
  "created_at": "2026-05-05T16:47:25+03:00"
}
```

Invalid body `422`:

```json
{
  "success": false,
  "message": "Invalid read status"
}
```

Invalid JSON `422`:

```json
{
  "success": false,
  "message": "Invalid JSON body"
}
```

Not found `404`:

```json
{
  "success": false,
  "message": "Not found"
}
```

### `DELETE /api/admin/messages/{id}`

Hard-deletes a contact message.

Example:

```text
DELETE /api/admin/messages/1
```

Success response `204`:

```text
No response body
```

### `GET /api/admin/stats`

Returns message counts for the admin dashboard.

Response `200`:

```json
{
  "total": 12,
  "unread": 3,
  "today": 2,
  "this_week": 8
}
```

## Common Error Responses

Route not found `404`:

```json
{
  "success": false,
  "message": "Not found"
}
```

Unauthorized or missing JWT `401`:

```json
{
  "success": false,
  "message": "Unauthorized"
}
```

Production server error `500`:

```json
{
  "success": false,
  "message": "Server error"
}
```

## Curl Examples

Health:

```bash
curl -i https://api.anomabebe.tech/api/health
```

Contact:

```bash
curl -i -X POST https://api.anomabebe.tech/api/contact \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "subject": "Project inquiry",
    "message": "Hello, I would like to talk about a project."
  }'
```

Login:

```bash
curl -i -X POST https://api.anomabebe.tech/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@anomabebe.tech",
    "password": "admin-password"
  }'
```

Protected request:

```bash
TOKEN="paste-jwt-token-here"

curl -i https://api.anomabebe.tech/api/admin/messages \
  -H "Authorization: Bearer $TOKEN"
```

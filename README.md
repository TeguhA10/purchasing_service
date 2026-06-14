# Purchasing Service (Microservice)

This microservice handles the purchasing workflow, including vendor directories, items catalog, and purchase order lifecycle management (Draft, Submitted, Approved, Rejected, Received, Cancelled).

## Tech Stack
- **Framework:** Laravel 13
- **PHP Version:** PHP 8.3
- **Database:** MySQL 8.4 (`db_purchasing`)
- **Authentication:** JWT (via middleware that validates tokens issued by the Auth Service)

---

## API Endpoints Reference

All endpoints are prefixed with `/api` and require a valid JWT cookie.

### General Authenticated Routes (Any valid user role)
- **Vendors (Read):**
  - `GET /api/vendors` - Lists vendors.
  - `GET /api/vendors/{id}` - Retrieves details of a specific vendor.
  - `GET /api/vendors/{id}/purchase-history` - Retrieves historical POs for a vendor.
- **Items (Read):**
  - `GET /api/items` - Lists catalog items.
  - `GET /api/items/{id}` - Retrieves specific item details.
- **Purchase Orders (Basic Actions):**
  - `POST /api/purchase-orders` - Creates a new draft Purchase Order.
  - `PUT /api/purchase-orders/{id}/items` - Adds/updates items inside a draft PO.
  - `PATCH /api/purchase-orders/{id}/submit` - Submits a draft PO for approval.
  - `PATCH /api/purchase-orders/{id}/cancel` - Cancels a draft or submitted PO.
  - `GET /api/purchase-orders` - Lists Purchase Orders.
  - `GET /api/purchase-orders/{id}` - Retrieves details of a specific PO.

### Purchasing Admin Routes (Requires admin_purchasing / superadmin Role)
- **Vendors (Write):**
  - `POST /api/vendors` - Register a vendor.
  - `PUT /api/vendors/{id}` - Update vendor details.
  - `PATCH /api/vendors/{id}/deactivate` - Deactivates a vendor.
  - `PATCH /api/vendors/{id}/activate` - Re-activates a vendor.
- **Items (Write):**
  - `POST /api/items` - Add a new item to catalog.
  - `PUT /api/items/{id}` - Update item details.
  - `PATCH /api/items/{id}/deactivate` - Deactivates an item.
  - `PATCH /api/items/{id}/activate` - Re-activates an item.
- **PO Workflow:**
  - `PATCH /api/purchase-orders/{id}/approve` - Approves a submitted PO.
  - `PATCH /api/purchase-orders/{id}/reject` - Rejects a submitted PO (requires `rejection_reason`).

### Branch Admin Routes (Requires admin_cabang / superadmin Role)
- **PO Workflow:**
  - `PATCH /api/purchase-orders/{id}/receive` - Marks an approved PO as received (updates item's `last_price` automatically).

---

## Environment Configuration

A `.env.example` file is provided. Key custom variables:
- `AUTH_SERVICE_URL`: Base URL of the Auth Service (for validating user accounts).
- `EMPLOYEE_SERVICE_URL`: Base URL of the Employee (HRIS) Service (for mapping branch and employee data).
- `JWT_ACCESS_SECRET`: Secret key for validating JWT tokens (must match Auth Service).
- `DB_DATABASE`: Defaults to `db_purchasing`.

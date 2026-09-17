# CampMart API v1 Documentation

**Base URL:** `http://host/campmartv2/api/v1`

---

## Quick Start (curl testing)

```powershell
# 1. Login to get a token
$TOKEN = curl.exe -s -X POST http://localhost/campmartv2/api/v1/auth/login `
  -H "Content-Type: application/json" `
  -d '{\"emailOrPhone\":\"your@email.com\",\"password\":\"yourpass\"}' `
  | C:\Windows\System32\findstr.exe access_token

# 2. Use the token in subsequent requests
curl.exe http://localhost/campmartv2/api/v1/auth/me -H "Authorization: Bearer $TOKEN"

# 3. List products (no token needed)
curl.exe http://localhost/campmartv2/api/v1/products

# 4. Search
curl.exe "http://localhost/campmartv2/api/v1/search?q=phone&type=product"
```

---

## Authentication

All protected endpoints require an `Authorization: Bearer <token>` header.

### Obtaining a Token

| Endpoint | Method | Auth |
|----------|--------|:----:|
| `/auth/login` | POST | No |
| `/auth/signup` | POST | No |
| `/auth/refresh` | POST | No |

### Token Types

| Token | Expiry | Usage |
|-------|--------|-------|
| `access_token` | 24 hours | `Authorization: Bearer <token>` |
| `refresh_token` | 30 days | Send in body to `/auth/refresh` |

---

## Response Format

### Success
```json
{
  "success": true,
  "message": "Success",
  "data": { ... }
}
```

### Error
```json
{
  "success": false,
  "message": "Error description"
}
```

### Paginated
```json
{
  "success": true,
  "message": "Success",
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total_items": 100,
    "total_pages": 5,
    "has_next": true,
    "has_prev": false
  }
}
```

> **Query params for paginated endpoints:** `?page=1&per_page=20`

---

## Authentication

### POST `/auth/signup` — Register a new user

**Request:**
```json
{
  "firstname": "John",
  "lastname": "Doe",
  "email": "john@example.com",
  "phone": "08012345678",
  "password": "password123",
  "university_id": 1
}
```

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Account created successfully",
  "data": {
    "user": {
      "id": 1,
      "username": "john1234",
      "firstname": "John",
      "lastname": "Doe",
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "08012345678",
      "role": "user"
    },
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

> **Note:** `university_id` is optional. It links the user to a specific university for localized browsing.

### POST `/auth/login` — Log in

**Request:**
```json
{
  "emailOrPhone": "john@example.com",
  "password": "password123"
}
```

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "username": "john1234",
      "email": "john@example.com",
      "firstname": "John",
      "lastname": "Doe",
      "full_name": "John Doe",
      "phone": "08012345678",
      "role": "user",
      "status": "active",
      "profile_image": null,
      "is_verified": 0
    },
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

> `emailOrPhone` accepts either an email address or a phone number.

### POST `/auth/logout` — Log out

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{ "success": true, "message": "Logged out successfully" }
```

> Server-side: returns success. Client should discard the tokens.

### POST `/auth/refresh` — Refresh tokens

**Request:**
```json
{
  "refresh_token": "eyJ..."
}
```

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

### GET `/auth/me` — Get current user

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 1,
    "username": "john1234",
    "email": "john@example.com",
    "full_name": "John Doe",
    "firstname": "John",
    "lastname": "Doe",
    "role": "user",
    "status": "active",
    "profile_image": null
  }
}
```

---

## Products

### GET `/products` — List products

**Auth:** Optional (if authenticated, filters by user's university)

**Query Params:**
| Param | Type | Description |
|-------|------|-------------|
| `category` | int | Filter by category ID |
| `search` | string | Search in title and description |
| `condition` | string | `new`, `like_new`, `good`, `fair` |
| `min_price` | float | Minimum price |
| `max_price` | float | Maximum price |
| `seller_id` | int | Filter by seller |
| `sort` | string | `price_low`, `price_high`, `popular`, `oldest` |
| `page` | int | Page number (default 1) |
| `per_page` | int | Items per page (default 20, max 100) |

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "title": "Product Name",
      "slug": "product-name",
      "price": "5000.00",
      "original_price": "7000.00",
      "condition_type": "new",
      "negotiable": 1,
      "available_quantity": 5,
      "sold_count": 2,
      "image": "uploads/products/xxx.webp",
      "seller": {
        "id": 2,
        "full_name": "Seller Name",
        "username": "seller123",
        "profile_image": null
      },
      "category_name": "Electronics",
      "is_bookmarked": false,
      "created_at": "2026-07-28 12:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total_items": 50,
    "total_pages": 3,
    "has_next": true,
    "has_prev": false
  }
}
```

> If no results match, `data` is an empty array `[]`.

### GET `/products/{id}` — Get product detail

**Auth:** Optional

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 1,
    "title": "Product Name",
    "slug": "product-name",
    "description": "Full product description...",
    "price": "5000.00",
    "original_price": "7000.00",
    "condition_type": "new",
    "negotiable": 1,
    "available_quantity": 5,
    "sold_count": 2,
    "view_count": 150,
    "delivery_fee": "500.00",
    "images": ["uploads/products/xxx.webp", "uploads/products/yyy.webp"],
    "category": {
      "id": 5,
      "name": "Electronics",
      "slug": "electronics"
    },
    "seller": {
      "id": 2,
      "full_name": "Seller Name",
      "username": "seller123",
      "profile_image": null,
      "phone": "08012345678",
      "university": "University of Lagos"
    },
    "is_bookmarked": false,
    "created_at": "2026-07-28 12:00:00"
  }
}
```

> View count is incremented each time this endpoint is called.

### POST `/products` — Create a product

**Headers:** `Authorization: Bearer <token>`
**Content-Type:** `multipart/form-data`

**Fields:**
| Field | Type | Required | Description |
|-------|------|:--------:|-------------|
| `title` | string | Yes | Product title |
| `category_id` | int | Yes | Category ID |
| `price` | float | Yes | Selling price |
| `description` | string | Yes | Product description |
| `original_price` | float | No | Original/comparison price |
| `condition_type` | string | No | `new`, `like_new`, `good`, `fair` |
| `negotiable` | int | No | `0` or `1` |
| `available_quantity` | int | No | Default 1 |
| `delivery_fee` | float | No | Delivery cost |
| `images[]` | file | No | Multiple image uploads |

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Product created successfully",
  "data": {
    "id": 10,
    "title": "Product Name",
    "slug": "product-name",
    "images": ["uploads/products/xxx.webp"]
  }
}
```

### PUT `/products/{id}` — Update a product

**Headers:** `Authorization: Bearer <token>`
**Content-Type:** `application/json`

**Request** (partial update — only send changed fields):
```json
{
  "title": "Updated Title",
  "price": 4500
}
```

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Product updated successfully"
}
```

> Only the owner can update. Unauthorized users get `403`.

### DELETE `/products/{id}` — Delete a product

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Product deleted successfully"
}
```

> Soft-delete: sets `availability=deleted` and `status=removed`.

---

## Categories

### GET `/categories` — List all categories

**Auth:** No

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "Electronics",
      "slug": "electronics",
      "icon": "laptop",
      "product_count": 25
    }
  ]
}
```

> Only active, top-level categories are returned with their product counts.

### GET `/categories/{id}` — Get category with subcategories

**Auth:** No

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 1,
    "name": "Electronics",
    "slug": "electronics",
    "icon": "laptop",
    "product_count": 25,
    "subcategories": [
      {
        "id": 10,
        "name": "Laptops",
        "slug": "laptops",
        "product_count": 10
      }
    ]
  }
}
```

---

## User Profile

### GET `/user/profile` — Get own profile

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 1,
    "username": "john1234",
    "email": "john@example.com",
    "firstname": "John",
    "lastname": "Doe",
    "full_name": "John Doe",
    "phone": "08012345678",
    "bio": "Student at UNILAG",
    "location": "Lagos",
    "department": "Computer Science",
    "level": "300",
    "profile_image": "uploads/avatars/xxx.webp",
    "role": "user",
    "university": "University of Lagos",
    "products_count": 5,
    "services_count": 2,
    "bookmarks_count": 8
  }
}
```

### PUT `/user/profile` — Update profile

**Headers:** `Authorization: Bearer <token>`

**Request:**
```json
{
  "firstname": "Johnny",
  "bio": "Updated bio",
  "location": "Abuja",
  "department": "Computer Engineering"
}
```

**Allowed fields:** `firstname`, `lastname`, `phone`, `bio`, `location`, `username`, `department`, `level`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Profile updated successfully"
}
```

### PUT `/user/password` — Change password

**Headers:** `Authorization: Bearer <token>`

**Request:**
```json
{
  "current_password": "oldpass123",
  "new_password": "newpass456",
  "confirm_password": "newpass456"
}
```

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

### POST `/user/avatar` — Upload avatar

**Headers:** `Authorization: Bearer <token>`
**Content-Type:** `multipart/form-data`

**Fields:**
| Field | Type | Required | Description |
|-------|------|:--------:|-------------|
| `avatar` | file | Yes | JPG, PNG, or WEBP. Max 5MB. |

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Avatar updated successfully",
  "data": {
    "avatar_url": "uploads/avatars/xxx.webp"
  }
}
```

---

## Bookmarks

### GET `/bookmarks` — List bookmarks

**Headers:** `Authorization: Bearer <token>`

**Query Params:** `page`, `per_page`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "product_id": 5,
      "service_id": null,
      "product": { ... },
      "service": null,
      "created_at": "2026-07-28 12:00:00"
    }
  ],
  "pagination": { ... }
}
```

> Each item has either `product` or `service` populated, the other is `null`.

### POST `/bookmarks` — Toggle bookmark

**Headers:** `Authorization: Bearer <token>`

**Request** (provide one of):
```json
{ "product_id": 5 }
```
```json
{ "service_id": 3 }
```

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Bookmarked successfully",
  "data": { "bookmarked": true }
}
```

> If already bookmarked, it removes the bookmark. Response indicates the new state.

---

## Cart

### GET `/cart` — Get cart items

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 5,
        "quantity": 2,
        "delivery_option": "delivery",
        "product": {
          "id": 5,
          "title": "Product Name",
          "price": "5000.00",
          "image": "uploads/products/xxx.webp",
          "seller": { ... }
        },
        "unit_price": "5000.00",
        "total_price": "10000.00"
      }
    ],
    "subtotal": "15000.00",
    "delivery_fee": "1000.00",
    "platform_fee": "750.00",
    "total": "16750.00"
  }
}
```

> Platform fee is 5% of subtotal, minimum 500.

### POST `/cart` — Add item to cart

**Headers:** `Authorization: Bearer <token>`

**Request:**
```json
{
  "product_id": 5,
  "quantity": 1,
  "delivery_option": "delivery"
}
```

> `delivery_option`: `"delivery"` or `"meeting"` (default: `"delivery"`).

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Item added to cart",
  "data": { "cart_item_id": 1 }
}
```

> If the product is already in the cart, it increments the quantity instead of adding a duplicate.

### PUT `/cart/{id}` — Update cart item

**Headers:** `Authorization: Bearer <token>`

**Request:**
```json
{
  "quantity": 3,
  "delivery_option": "meeting"
}
```

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Cart item updated"
}
```

### DELETE `/cart/{id}` — Remove cart item

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Item removed from cart"
}
```

---

## Orders

### GET `/orders` — List orders

**Headers:** `Authorization: Bearer <token>`

**Query Params:**
| Param | Type | Default | Description |
|-------|------|:-------:|-------------|
| `role` | string | `buyer` | `buyer` or `seller` |
| `page` | int | 1 | |
| `per_page` | int | 20 | |

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "order_number": "ORD-20260728-001",
      "product_id": 5,
      "product_name": "Product Name",
      "quantity": 1,
      "unit_price": "5000.00",
      "total_price": "5500.00",
      "status": "pending",
      "delivery_option": "delivery",
      "buyer": { "id": 1, "full_name": "John Doe" },
      "seller": { "id": 2, "full_name": "Jane Smith" },
      "created_at": "2026-07-28 12:00:00"
    }
  ],
  "pagination": { ... }
}
```

### GET `/orders/{id}` — Get order detail

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`: Full order detail including buyer/seller info, product snapshot, and status history.

### POST `/orders` — Place order(s)

**Headers:** `Authorization: Bearer <token>`

**Request:** (empty body — orders are created from current cart contents)

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Orders placed successfully",
  "data": {
    "order_ids": [1, 2, 3],
    "order_numbers": ["ORD-20260728-001", "ORD-20260728-002"],
    "total": "16500.00"
  }
}
```

> Creates individual orders per cart item, deducts stock, clears the cart. Payment method is Cash On Delivery.

---

## Services

### GET `/services` — List services

**Auth:** No

**Query Params:**
| Param | Type | Description |
|-------|------|-------------|
| `category` | int | Filter by service category ID |
| `search` | string | Search in title and description |
| `min_price` | float | Minimum price |
| `max_price` | float | Maximum price |
| `pricing_type` | string | `fixed`, `hourly`, `negotiable` |
| `availability` | string | `available`, `busy` |
| `provider_id` | int | Filter by provider |
| `sort` | string | `price_low`, `price_high`, `popular`, `rating`, `oldest` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "title": "Web Development",
      "slug": "web-development",
      "description": "I build modern websites...",
      "price": "50000.00",
      "pricing_type": "fixed",
      "delivery_time": "7 days",
      "image": "uploads/services/xxx.webp",
      "provider": {
        "id": 2,
        "full_name": "Jane Smith",
        "username": "jane123",
        "profile_image": null
      },
      "category_name": "Web Development",
      "average_rating": 4.5,
      "review_count": 12,
      "is_bookmarked": false,
      "created_at": "2026-07-28 12:00:00"
    }
  ],
  "pagination": { ... }
}
```

### GET `/services/{id}` — Get service detail

**Auth:** No

**Response** `200 OK`: Full service detail with provider info, portfolio images, skills, reviews.

### POST `/services` — Create a service

**Headers:** `Authorization: Bearer <token>`
**Content-Type:** `multipart/form-data`

**Fields:**
| Field | Type | Required | Description |
|-------|------|:--------:|-------------|
| `title` | string | Yes | Service title |
| `service_category_id` | int | Yes | Category ID |
| `price` | float | Yes | Price |
| `description` | string | Yes | Full description |
| `short_description` | string | No | Brief summary |
| `pricing_type` | string | No | `fixed`, `hourly`, `negotiable` |
| `delivery_time` | string | No | e.g. "3 days", "1 week" |
| `portfolio_images[]` | file | No | Portfolio images |
| `skills` | string | No | Comma-separated skills |

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Service created successfully",
  "data": { "id": 1, "title": "Web Development" }
}
```

### PUT `/services/{id}` — Update a service

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Service updated successfully"
}
```

### DELETE `/services/{id}` — Delete a service

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Service deleted successfully"
}
```

> Soft-delete: sets `status=inactive`.

### GET `/service-categories` — List service categories

**Auth:** No

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    { "id": 1, "name": "Web Development", "slug": "web-development", "service_count": 15 }
  ]
}
```

---

## Reviews

### GET `/reviews?product_id={id}` — Product reviews

**Auth:** No

**Query Params:** `?product_id=5` or `?service_id=3` or `?user_id=2`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "reviewer": { "id": 1, "full_name": "John Doe", "profile_image": null },
      "rating": 5,
      "review_text": "Great product!",
      "images": [],
      "created_at": "2026-07-28 12:00:00"
    }
  ],
  "pagination": { ... }
}
```

> Only approved reviews are returned.

### POST `/reviews` — Create a review

**Headers:** `Authorization: Bearer <token>`

**Request:**
```json
{
  "reviewed_user_id": 2,
  "rating": 5,
  "review_type": "product",
  "transaction_id": 1,
  "product_id": 5,
  "review_text": "Excellent quality!",
  "images": []
}
```

> `review_type`: `"product"`, `"service"`, `"seller"`, or `"buyer"`.

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Review submitted successfully"
}
```

### DELETE `/reviews/{id}` — Delete own review

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Review deleted"
}
```

---

## Notifications

### GET `/notifications` — List notifications

**Headers:** `Authorization: Bearer <token>`

**Query Params:** `type`, `is_read` (0/1), `page`, `per_page`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "title": "New Message",
      "message": "You have a new message from Jane",
      "type": "message",
      "related_id": 5,
      "related_type": "conversation",
      "is_read": 0,
      "created_at": "2026-07-28 12:00:00"
    }
  ],
  "pagination": { ... }
}
```

### GET `/notifications/count` — Unread count

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": { "unread": 3 }
}
```

### PUT `/notifications/{id}` — Mark as read

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

### PUT `/notifications/read-all` — Mark all as read

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "All notifications marked as read"
}
```

### DELETE `/notifications/{id}` — Delete notification

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Notification deleted"
}
```

---

## Chats / Messages

### GET `/chats` — List conversations

**Headers:** `Authorization: Bearer <token>`

**Query Params:** `page`, `per_page`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "other_user": {
        "id": 2,
        "full_name": "Jane Smith",
        "username": "jane123",
        "profile_image": null
      },
      "last_message": {
        "id": 10,
        "message": "Hello!",
        "sender_id": 2,
        "created_at": "2026-07-28 12:00:00",
        "is_read": 0
      },
      "unread_count": 1,
      "product": null,
      "service": null,
      "updated_at": "2026-07-28 12:00:00"
    }
  ],
  "pagination": { ... }
}
```

### GET `/chats/count` — Unread message count

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": { "unread": 3 }
}
```

### POST `/chats` — Start a conversation

**Headers:** `Authorization: Bearer <token>`

**Request:**
```json
{
  "user_id": 2,
  "message": "Hi, I'm interested in your product",
  "product_id": 5,
  "service_id": null
}
```

> If a conversation already exists with the same user/product pair, the existing conversation is reused.

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Conversation started",
  "data": { "conversation_id": 1, "message_id": 1 }
}
```

### GET `/chats/{conversation_id}` — Get messages

**Headers:** `Authorization: Bearer <token>`

**Query Params:** `page`, `per_page` (default 50)

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "conversation_id": 1,
      "sender_id": 1,
      "message": "Hello!",
      "is_read": 1,
      "created_at": "2026-07-28 12:00:00",
      "sender": { "id": 1, "full_name": "John Doe", "profile_image": null }
    }
  ],
  "pagination": { ... }
}
```

### POST `/chats/{conversation_id}/messages` — Send a message

**Headers:** `Authorization: Bearer <token>`

**Request:**
```json
{
  "message": "Sure, when can you deliver?",
  "attachment_url": null
}
```

**Response** `201 Created`:
```json
{
  "success": true,
  "message": "Message sent",
  "data": {
    "id": 11,
    "conversation_id": 1,
    "sender_id": 1,
    "message": "Sure, when can you deliver?",
    "created_at": "2026-07-28 12:00:00"
  }
}
```

### PUT `/chats/{conversation_id}/read` — Mark as read

**Headers:** `Authorization: Bearer <token>`

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Messages marked as read",
  "data": { "updated": 3 }
}
```

---

## Search

### GET `/search` — Global search

**Auth:** No

**Query Params:**
| Param | Required | Description |
|-------|:--------:|-------------|
| `q` | Yes | Search query |
| `type` | No | `all` (default), `product`, `service`, `user` |

**Response** `200 OK`:
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "products": [
      {
        "id": 1,
        "title": "iPhone 13",
        "price": "350000.00",
        "image": "uploads/products/xxx.webp",
        "url": "/campmartv2/product-detail.php?slug=iphone-13"
      }
    ],
    "services": [
      {
        "id": 1,
        "title": "Web Development",
        "price": "50000.00",
        "image": "uploads/services/xxx.webp",
        "url": "/campmartv2/service-detail.php?slug=web-development"
      }
    ],
    "users": [
      {
        "id": 2,
        "full_name": "Jane Smith",
        "username": "jane123",
        "profile_image": null,
        "url": "/campmartv2/profile.php?username=jane123"
      }
    ]
  }
}
```

> When `type=product`, only `products` is returned. Same for `service` and `user`. When `type=all`, all three sections are returned (max 10 per section).

---

## Users

### GET `/users/{id}/services` — User's services

**Auth:** No

**Response** `200 OK`: Paginated list of active services by the specified user.

---

## HTTP Status Codes

| Code | Meaning |
|:----:|---------|
| 200 | Success (GET, PUT, DELETE) |
| 201 | Created (POST) |
| 400 | Bad request / Validation error |
| 401 | Missing or invalid token |
| 403 | Forbidden (suspended/banned/not owner) |
| 404 | Resource not found |
| 405 | Method not allowed |
| 409 | Conflict (duplicate email/phone) |
| 422 | Unprocessable entity (missing fields) |
| 500 | Server error |

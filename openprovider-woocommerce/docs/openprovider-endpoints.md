# OpenProvider Endpoints Reference

## Base URLs

- **Production**: `https://api.openprovider.eu/v1beta`
- **Sandbox (CTE)**: `https://api.cte.openprovider.eu/v1beta`

## Endpoints Used

### POST /auth/login

**Request:**
```json
{
  "username": "string",
  "password": "string",
  "ip": "optional"
}
```

**Response (assumed structure - verify in sandbox):**
```json
{
  "data": {
    "token": "string",
    "resellerId": "string",
    "expiresIn": 3600
  }
}
```

### POST /domains/check

**Request:**
```json
{
  "domains": [
    {
      "name": "example",
      "extension": "com"
    }
  ]
}
```

**Response (assumed structure - verify in sandbox):**
```json
{
  "data": {
    "domains": [
      {
        "name": "example",
        "extension": "com",
        "status": "free|active|taken",
        "premium": false,
        "price": {
          "value": 9.99,
          "currency": "EUR"
        }
      }
    ]
  }
}
```

### GET /domains/prices

**Query Parameters:**
- `domain.name`: Domain name without extension
- `domain.extension`: TLD (e.g., "com")
- `operation`: "create", "renew", or "transfer"
- `period`: Registration period in years (1-10)
- `additional_data.idn_script`: Optional IDN script

**Response (assumed structure - verify in sandbox):**
```json
{
  "data": {
    "price": 9.99,
    "currency": "EUR",
    "premium": false
  }
}
```

### POST /domains

**Request:**
```json
{
  "name": "example",
  "extension": "com",
  "period": 1,
  "owner": { "handle": "handle123" },
  "admin": { "handle": "handle123" },
  "tech": { "handle": "handle123" },
  "billing": { "handle": "handle123" },
  "additional_data": {
    "it": {
      "entity_type": "individual|company",
      "identification_code": "string"
    }
  }
}
```

**Response (assumed structure - verify in sandbox):**
```json
{
  "data": {
    "id": "domain_id",
    "orderId": "order_id",
    "status": "pending"
  }
}
```

## Notes

- All responses use the `data` wrapper (based on typical OpenProvider API patterns)
- Field names in the mapping methods (`map_*_response()`) handle variations
- Verify exact field names against the sandbox during initial testing
- Update this document as field names are confirmed

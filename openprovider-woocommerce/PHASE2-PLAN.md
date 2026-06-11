# OpenProvider WooCommerce - Phase 2 Detailed Implementation Plan

## Overview

This document provides detailed implementation specifications for Phase 2 features, including file structures, method signatures, database schemas, API specifications, and testing requirements.

---

## 2.1 Transfer Workflow

### Goal
Enable customers to transfer existing domains from other registrars to OpenProvider via WooCommerce.

### User Flow
1. Customer enters domain name on transfer search form
2. System checks transfer eligibility via OpenProvider API
3. Customer enters auth/EPP code
4. Domain + auth code added to cart as virtual product
5. Checkout completes → transfer initiated with OpenProvider
6. Transfer status tracked (pending → in_progress → completed/failed)

### Files to Create

```
src/Api/
├── TransferService.php          # OpenProvider transfer API client

src/WooCommerce/
├── TransferIntegration.php      # Cart/order hooks for transfers
├── TransferProductFactory.php   # Transfer virtual product

src/Rest/
├── TransferController.php       # REST endpoints for transfer

templates/
└── shortcode-domain-transfer.php

assets/
├── css/domain-transfer.css
└── js/domain-transfer.js

tests/unit/Api/
└── TransferServiceTest.php
```

### `src/Api/TransferService.php`

```php
<?php
namespace OpenProviderWooCommerce\Api;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

class TransferService {
    private HttpClientInterface $http;
    private AuthService $auth;
    private Settings $settings;
    private Logger $logger;

    public function __construct(
        HttpClientInterface $http,
        AuthService $auth,
        Settings $settings,
        Logger $logger
    );

    /**
     * Check if domain can be transferred.
     *
     * @param string $name Domain name.
     * @param string $tld TLD.
     * @return array{available: bool, price: float, currency: string, requires_auth_code: bool}
     */
    public function check_transfer(string $name, string $tld): array;

    /**
     * Initiate domain transfer.
     *
     * @param array $data Transfer data.
     * @return array{transfer_id: string, status: string, estimated_completion: ?string}
     */
    public function initiate_transfer(array $data): array;

    /**
     * Get transfer status.
     *
     * @param string $transfer_id Transfer ID from OpenProvider.
     * @return array{status: string, progress: int, message: ?string}
     */
    public function get_transfer_status(string $transfer_id): array;

    /**
     * Complete transfer with auth code (if required).
     *
     * @param string $transfer_id Transfer ID.
     * @param string $auth_code EPP/auth code.
     * @return array{success: bool, message: string}
     */
    public function complete_transfer(string $transfer_id, string $auth_code): array;

    /**
     * Cancel pending transfer.
     *
     * @param string $transfer_id Transfer ID.
     * @return array{success: bool, message: string}
     */
    public function cancel_transfer(string $transfer_id): array;

    private function map_check_response(array $raw): array;
    private function map_initiate_response(array $raw): array;
    private function map_status_response(array $raw): array;
}
```

### OpenProvider API Endpoints (Transfer)

**POST /v1beta/transfers/check**
```json
Request:
{
  "name": "example",
  "extension": "com"
}

Response:
{
  "data": {
    "transferable": true,
    "price": {
      "value": 9.99,
      "currency": "EUR"
    },
    "requiresAuthCode": true,
    "estimatedDays": 5
  }
}
```

**POST /v1beta/transfers**
```json
Request:
{
  "name": "example",
  "extension": "com",
  "authCode": "ABC123XYZ",
  "owner": {"handle": "handle123"},
  "admin": {"handle": "handle123"},
  "tech": {"handle": "handle123"},
  "billing": {"handle": "handle123"}
}

Response:
{
  "data": {
    "id": "transfer_abc123",
    "status": "pending_approval",
    "createdAt": "2026-01-15T10:30:00Z"
  }
}
```

**GET /v1beta/transfers/{id}**
```json
Response:
{
  "data": {
    "id": "transfer_abc123",
    "status": "in_progress",
    "progress": 50,
    "estimatedCompletion": "2026-01-20T10:30:00Z"
  }
}
```

### Database Schema Changes

```sql
ALTER TABLE {$wpdb->prefix}op_domains 
ADD COLUMN transfer_auth_code VARCHAR(255) DEFAULT NULL,
ADD COLUMN transfer_id VARCHAR(64) DEFAULT NULL,
ADD COLUMN transfer_status VARCHAR(32) DEFAULT 'pending',
ADD COLUMN transfer_initiated_at DATETIME DEFAULT NULL,
ADD COLUMN transfer_completed_at DATETIME DEFAULT NULL;

-- Indexes for transfer queries
CREATE INDEX transfer_id_idx ON {$wpdb->prefix}op_domains(transfer_id);
CREATE INDEX transfer_status_idx ON {$wpdb->prefix}op_domains(transfer_status);
```

### `src/Rest/TransferController.php` Routes

```php
// GET /openprovider-woocommerce/v1/transfer/check
// Check transfer eligibility
// Rate limit: 20/min
// Permission: public

// POST /openprovider-woocommerce/v1/transfer/cart/add
// Add transfer to cart (requires auth code)
// Rate limit: 10/min
// Permission: public

// GET /openprovider-woocommerce/v1/transfer/status/{transfer_id}
// Check transfer status
// Rate limit: 30/min
// Permission: public (but only show own transfers if logged in)

// POST /openprovider-woocommerce/v1/transfer/complete
// Complete transfer with auth code
// Rate limit: 5/min
// Permission: logged-in user (must own the transfer)
```

### `templates/shortcode-domain-transfer.php`

```php
<div class="opwc-transfer-search" data-allowed-tlds="<?php echo esc_attr( json_encode( $allowed_tlds ) ); ?>">
    <div class="opwc-transfer-form">
        <input type="text" class="opwc-transfer-domain" placeholder="example.com" />
        <button type="button" class="opwc-transfer-check-btn"><?php esc_html_e( 'Check Transfer', 'openprovider-woocommerce' ); ?></button>
    </div>
    
    <div class="opwc-transfer-result" style="display: none;">
        <div class="opwc-transfer-price"></div>
        <div class="opwc-transfer-auth-code-input" style="display: none;">
            <label><?php esc_html_e( 'Enter Auth/EPP Code:', 'openprovider-woocommerce' ); ?></label>
            <input type="text" class="opwc-auth-code" />
        </div>
        <button type="button" class="opwc-transfer-add-cart" style="display: none;">
            <?php esc_html_e( 'Add to Cart', 'openprovider-woocommerce' ); ?>
        </button>
    </div>
    
    <div class="opwc-transfer-result-error" style="display: none;"></div>
</div>
```

### Testing Requirements

- `TransferServiceTest::test_check_transfer_returns_eligibility`
- `TransferServiceTest::test_initiate_transfer_creates_transfer`
- `TransferServiceTest::test_get_transfer_status_returns_progress`
- `TransferServiceTest::test_complete_transfer_with_auth_code`
- `TransferServiceTest::test_cancel_transfer`
- Integration test: Transfer flow from search to cart to order

---

## 2.2 Renewal Workflow

### Goal
Enable domain renewals via WooCommerce with auto-renewal support and expiry notifications.

### User Flow
1. Customer views domains in My Account → My Domains
2. Clicks "Renew" on a domain
3. Selects renewal period (1-10 years)
4. Domain added to cart as renewal line item
5. Checkout completes → renewal initiated with OpenProvider
6. Domain expiry date updated in `wp_op_domains`

### Files to Create

```
src/Api/
└── RenewalService.php

src/WooCommerce/
├── RenewalIntegration.php
├── RenewalNotifier.php              # Expiry email notifications
└── MyAccountRenewals.php            # My Account tab integration

src/Rest/
└── RenewalController.php

templates/
├── shortcode-domain-renewal.php
└── my-account-domains.php

assets/
├── css/my-account-domains.css
└── js/my-account-domains.js
```

### `src/Api/RenewalService.php`

```php
<?php
namespace OpenProviderWooCommerce\Api;

class RenewalService {
    private HttpClientInterface $http;
    private AuthService $auth;
    private Settings $settings;
    private Logger $logger;

    public function __construct(
        HttpClientInterface $http,
        AuthService $auth,
        Settings $settings,
        Logger $logger
    );

    /**
     * Check renewal price for a domain.
     *
     * @param string $name Domain name.
     * @param string $tld TLD.
     * @param int    $period Renewal period in years.
     * @return array{price: float, currency: string, current_expiry: string}
     */
    public function get_renewal_price(string $name, string $tld, int $period = 1): array;

    /**
     * Renew a domain.
     *
     * @param array $data Renewal data.
     * @return array{success: bool, new_expiry: string, order_id: string}
     */
    public function renew_domain(array $data): array;

    /**
     * Get domain details (including expiry).
     *
     * @param string $domain_id OpenProvider domain ID.
     * @return array{name: string, extension: string, expiry_date: string, status: string}
     */
    public function get_domain_details(string $domain_id): array;

    /**
     * Enable auto-renewal for a domain.
     *
     * @param string $domain_id Domain ID.
     * @return array{success: bool}
     */
    public function enable_auto_renewal(string $domain_id): array;

    /**
     * Disable auto-renewal for a domain.
     *
     * @param string $domain_id Domain ID.
     * @return array{success: bool}
     */
    public function disable_auto_renewal(string $domain_id): array;

    private function map_price_response(array $raw): array;
    private function map_renew_response(array $raw): array;
    private function map_details_response(array $raw): array;
}
```

### Database Schema Changes

```sql
ALTER TABLE {$wpdb->prefix}op_domains
ADD COLUMN auto_renew TINYINT(1) DEFAULT 0,
ADD COLUMN renewal_period INT DEFAULT 1,
MODIFY COLUMN expires_at DATETIME NULL;

-- Table for renewal notifications
CREATE TABLE {$wpdb->prefix}op_domain_notifications (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    domain_id BIGINT(20) UNSIGNED NOT NULL,
    notification_type VARCHAR(32) NOT NULL, -- 'expiry_30', 'expiry_14', 'expiry_7', 'expiry_1'
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY domain_id (domain_id),
    KEY sent_at (sent_at)
) {$charset_collate};
```

### `src/WooCommerce/RenewalNotifier.php`

```php
<?php
namespace OpenProviderWooCommerce\WooCommerce;

class RenewalNotifier {
    private Settings $settings;
    private Logger $logger;

    public function __construct(Settings $settings, Logger $logger);

    /**
     * Register scheduled hook.
     */
    public function register(): void;

    /**
     * Check for expiring domains and send notifications.
     * Called via WP Cron daily.
     */
    public function check_expiring_domains(): void;

    /**
     * Send expiry notification for a domain.
     *
     * @param object $domain Domain record.
     * @param int    $days_until_expiry Days until expiry.
     */
    private function send_expiry_notification(object $domain, int $days_until_expiry): void;

    /**
     * Mark notification as sent.
     *
     * @param int    $domain_id Domain ID.
     * @param string $type Notification type.
     */
    private function mark_notification_sent(int $domain_id, string $type): void;
}
```

### `src/WooCommerce/MyAccountRenewals.php`

```php
<?php
namespace OpenProviderWooCommerce\WooCommerce;

class MyAccountRenewals {
    private DomainRepository $repository;
    private RenewalService $renewal_service;
    private Settings $settings;

    public function __construct(
        DomainRepository $repository,
        RenewalService $renewal_service,
        Settings $settings
    );

    /**
     * Register hooks for My Account integration.
     */
    public function register(): void;

    /**
     * Add "My Domains" tab to My Account.
     */
    public function add_my_domains_tab(array $tabs): array;

    /**
     * Render My Domains endpoint content.
     */
    public function render_my_domains_endpoint(): void;

    /**
     * Get domains for current user.
     *
     * @param int    $user_id User ID.
     * @param int    $page Page number.
     * @param int    $per_page Items per page.
     * @return array{domains: array, total: int, pages: int}
     */
    public function get_user_domains(int $user_id, int $page = 1, int $per_page = 20): array;

    /**
     * Get domain details for display.
     *
     * @param int $domain_id Domain ID.
     * @return array|null
     */
    public function get_domain_for_display(int $domain_id): ?array;
}
```

### `templates/my-account-domains.php`

```php
<?php
/**
 * My Account → My Domains template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$domains = $this->get_user_domains( get_current_user_id() );
?>

<div class="opwc-my-domains">
    <h2><?php esc_html_e( 'My Domains', 'openprovider-woocommerce' ); ?></h2>

    <?php if ( empty( $domains['domains'] ) ) : ?>
        <p><?php esc_html_e( 'You have no registered domains.', 'openprovider-woocommerce' ); ?></p>
    <?php else : ?>
        <table class="opwc-domains-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Domain', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Expiry Date', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Auto-Renew', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'openprovider-woocommerce' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $domains['domains'] as $domain ) : ?>
                    <tr class="opwc-domain-row status-<?php echo esc_attr( $domain->status ); ?>">
                        <td>
                            <?php echo esc_html( $domain->domain_name . '.' . $domain->tld ); ?>
                        </td>
                        <td>
                            <span class="opwc-domain-status status-<?php echo esc_attr( $domain->status ); ?>">
                                <?php echo esc_html( ucfirst( $domain->status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            if ( $domain->expires_at ) :
                                $expiry = new \DateTime( $domain->expires_at );
                                $now = new \DateTime();
                                $days = $now->diff( $expiry )->days;
                                echo esc_html( $expiry->format( 'Y-m-d' ) );
                                if ( $days <= 30 ) :
                                    echo ' <small class="opwc-expiry-warning">(' . $days . ' days)</small>';
                                endif;
                            else :
                                esc_html_e( 'N/A', 'openprovider-woocommerce' );
                            endif;
                            ?>
                        </td>
                        <td>
                            <label class="opwc-auto-renew-toggle">
                                <input type="checkbox" 
                                       name="auto_renew" 
                                       value="1" 
                                       <?php checked( $domain->auto_renew ); ?>
                                       data-domain-id="<?php echo esc_attr( $domain->id ); ?>" />
                                <?php esc_html_e( 'Enabled', 'openprovider-woocommerce' ); ?>
                            </label>
                        </td>
                        <td>
                            <a href="#" class="opwc-renew-domain-btn" data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
                                <?php esc_html_e( 'Renew', 'openprovider-woocommerce' ); ?>
                            </a>
                            <a href="#" class="opwc-view-details-btn" data-domain-id="<?php echo esc_attr( $domain->id ); ?>">
                                <?php esc_html_e( 'Details', 'openprovider-woocommerce' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $domains['pages'] > 1 ) : ?>
            <div class="opwc-pagination">
                <?php echo paginate_links( array(
                    'total' => $domains['pages'],
                    'current' => max( 1, get_query_var( 'paged' ) ),
                ) ); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
```

### Testing Requirements

- `RenewalServiceTest::test_get_renewal_price_returns_price_and_expiry`
- `RenewalServiceTest::test_renew_domain_updates_expiry`
- `RenewalServiceTest::test_enable_disable_auto_renewal`
- `RenewalNotifierTest::test_sends_expiry_notifications`
- `RenewalNotifierTest::test_marks_notifications_as_sent`
- Integration test: Renewal flow from My Account to cart to order

---

## 2.3 DNS Management

### Goal
Allow customers to manage nameservers and DNS records for their domains.

### User Flow
1. Customer views domain details in My Account
2. Clicks "Manage DNS" or "Nameservers"
3. Edit nameservers or DNS records
4. Changes saved → updated via OpenProvider API
5. Confirmation shown with propagation warning

### Files to Create

```
src/Api/
└── DnsService.php

src/WooCommerce/
└── DnsIntegration.php

src/Rest/
└── DnsController.php

templates/
└── my-account-dns.php

assets/
├── css/dns-manager.css
└── js/dns-manager.js
```

### `src/Api/DnsService.php`

```php
<?php
namespace OpenProviderWooCommerce\Api;

class DnsService {
    private HttpClientInterface $http;
    private AuthService $auth;
    private Settings $settings;
    private Logger $logger;

    public function __construct(
        HttpClientInterface $http,
        AuthService $auth,
        Settings $settings,
        Logger $logger
    );

    /**
     * Get nameservers for a domain.
     *
     * @param string $domain_id Domain ID.
     * @return array{type: string, servers: array} type: 'default' | 'custom'
     */
    public function get_nameservers(string $domain_id): array;

    /**
     * Update nameservers for a domain.
     *
     * @param string   $domain_id Domain ID.
     * @param array    $nameservers Array of nameserver hostnames.
     * @return array{success: bool, message: string}
     */
    public function update_nameservers(string $domain_id, array $nameservers): array;

    /**
     * Reset nameservers to OpenProvider defaults.
     *
     * @param string $domain_id Domain ID.
     * @return array{success: bool, message: string}
     */
    public function reset_nameservers(string $domain_id): array;

    /**
     * Get DNS records for a domain.
     *
     * @param string $domain_id Domain ID.
     * @param string|null $type Filter by record type (A, AAAA, CNAME, MX, TXT, SRV, NS, SOA).
     * @return array DNS records.
     */
    public function get_dns_records(string $domain_id, ?string $type = null): array;

    /**
     * Add DNS record.
     *
     * @param array $record Record data.
     * @return array{id: string, success: bool}
     */
    public function add_dns_record(array $record): array;

    /**
     * Update DNS record.
     *
     * @param string $record_id Record ID.
     * @param array  $data Record data to update.
     * @return array{success: bool}
     */
    public function update_dns_record(string $record_id, array $data): array;

    /**
     * Delete DNS record.
     *
     * @param string $record_id Record ID.
     * @return array{success: bool}
     */
    public function delete_dns_record(string $record_id): array;

    /**
     * Get supported DNS record types.
     *
     * @return array List of supported types.
     */
    public function get_supported_types(): array;

    private function map_nameservers_response(array $raw): array;
    private function map_records_response(array $raw): array;
}
```

### DNS Record Types Supported

| Type | Description | Required Fields |
|------|-------------|-----------------|
| A | IPv4 address | name, value (IP) |
| AAAA | IPv6 address | name, value (IPv6) |
| CNAME | Alias | name, value (hostname) |
| MX | Mail exchange | name, priority, value (hostname) |
| TXT | Text record | name, value (text) |
| SRV | Service record | name, priority, weight, port, value |
| NS | Nameserver | name, value (nameserver) |
| SOA | Start of authority | (read-only, managed by OpenProvider) |

### `templates/my-account-dns.php`

```php
<?php
/**
 * My Account → DNS Management template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$domain_id = get_query_var( 'opwc_domain_id' );
$domain = $this->get_domain_for_display( $domain_id );
?>

<div class="opwc-dns-manager" data-domain-id="<?php echo esc_attr( $domain_id ); ?>">
    <h2><?php echo esc_html( $domain['domain_name'] . '.' . $domain['tld'] ); ?></h2>

    <!-- Nameservers Tab -->
    <div class="opwc-dns-section opwc-nameservers-section">
        <h3><?php esc_html_e( 'Nameservers', 'openprovider-woocommerce' ); ?></h3>
        
        <div class="opwc-nameserver-type">
            <label>
                <input type="radio" name="ns_type" value="default" <?php checked( $nameservers['type'], 'default' ); ?> />
                <?php esc_html_e( 'Use OpenProvider nameservers', 'openprovider-woocommerce' ); ?>
            </label>
            <label>
                <input type="radio" name="ns_type" value="custom" <?php checked( $nameservers['type'], 'custom' ); ?> />
                <?php esc_html_e( 'Use custom nameservers', 'openprovider-woocommerce' ); ?>
            </label>
        </div>

        <div class="opwc-custom-nameservers" style="display: <?php echo $nameservers['type'] === 'custom' ? 'block' : 'none'; ?>">
            <?php foreach ( $nameservers['servers'] as $index => $server ) : ?>
                <input type="text" class="opwc-nameserver-input" value="<?php echo esc_attr( $server ); ?>" />
            <?php endforeach; ?>
            <button type="button" class="opwc-add-nameserver-btn"><?php esc_html_e( 'Add Nameserver', 'openprovider-woocommerce' ); ?></button>
        </div>

        <button type="button" class="opwc-save-nameservers-btn"><?php esc_html_e( 'Save Nameservers', 'openprovider-woocommerce' ); ?></button>
    </div>

    <!-- DNS Records Tab -->
    <div class="opwc-dns-section opwc-records-section">
        <h3><?php esc_html_e( 'DNS Records', 'openprovider-woocommerce' ); ?></h3>

        <table class="opwc-dns-records-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Type', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'TTL', 'openprovider-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'openprovider-woocommerce' ); ?></th>
                </tr>
            </thead>
            <tbody class="opwc-dns-records-list">
                <?php foreach ( $records as $record ) : ?>
                    <tr data-record-id="<?php echo esc_attr( $record['id'] ); ?>">
                        <td><span class="opwc-record-type"><?php echo esc_html( $record['type'] ); ?></span></td>
                        <td><?php echo esc_html( $record['name'] ); ?></td>
                        <td><?php echo esc_html( $record['value'] ); ?></td>
                        <td><?php echo esc_html( $record['ttl'] ); ?></td>
                        <td>
                            <button type="button" class="opwc-edit-record-btn"><?php esc_html_e( 'Edit', 'openprovider-woocommerce' ); ?></button>
                            <button type="button" class="opwc-delete-record-btn"><?php esc_html_e( 'Delete', 'openprovider-woocommerce' ); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="button" class="opwc-add-record-btn"><?php esc_html_e( 'Add DNS Record', 'openprovider-woocommerce' ); ?></button>
    </div>

    <!-- Propagation Warning -->
    <div class="opwc-propagation-warning" style="display: none;">
        <p><?php esc_html_e( 'DNS changes may take up to 48 hours to propagate across the internet.', 'openprovider-woocommerce' ); ?></p>
    </div>
</div>
```

### REST Endpoints (DNS)

```php
// GET /openprovider-woocommerce/v1/domain/{id}/nameservers
// Get nameservers
// Permission: current user must own domain

// PUT /openprovider-woocommerce/v1/domain/{id}/nameservers
// Update nameservers
// Permission: current user must own domain

// GET /openprovider-woocommerce/v1/domain/{id}/dns-records
// Get DNS records
// Permission: current user must own domain

// POST /openprovider-woocommerce/v1/dns/record
// Add DNS record
// Permission: current user must own domain

// PUT /openprovider-woocommerce/v1/dns/record/{id}
// Update DNS record
// Permission: current user must own domain

// DELETE /openprovider-woocommerce/v1/dns/record/{id}
// Delete DNS record
// Permission: current user must own domain
```

### Testing Requirements

- `DnsServiceTest::test_get_nameservers_returns_type_and_servers`
- `DnsServiceTest::test_update_nameservers_saves_custom`
- `DnsServiceTest::test_get_dns_records_returns_all_types`
- `DnsServiceTest::test_add_update_delete_dns_record`
- Integration test: Full DNS management flow in My Account

---

## 2.4 My Account Domain Panel

### Goal
Customer-facing dashboard for managing all registered domains.

### Files to Create

```
src/WooCommerce/
└── MyAccountDomains.php

templates/
├── my-account-domains.php
├── my-account-domain-details.php
└── my-account-domain-renew.php
```

### `src/WooCommerce/MyAccountDomains.php`

```php
<?php
namespace OpenProviderWooCommerce\WooCommerce;

class MyAccountDomains {
    private DomainRepository $repository;
    private RenewalService $renewal_service;
    private Settings $settings;

    public function __construct(
        DomainRepository $repository,
        RenewalService $renewal_service,
        Settings $settings
    );

    public function register(): void;

    /**
     * Add "My Domains" tab to WooCommerce My Account.
     */
    public function add_tab(array $endpoints): array;

    /**
     * Add "My Domains" menu item.
     */
    public function add_menu_item(array $items): array;

    /**
     * Check if current user can access domain.
     *
     * @param int $domain_id Domain ID.
     * @return bool
     */
    public function can_access_domain(int $domain_id): bool;

    /**
     * Get paginated domains for user.
     */
    public function get_user_domains(int $user_id, int $page, int $per_page): array;

    /**
     * Get domain with full details.
     */
    public function get_domain_details(int $domain_id): ?array;

    /**
     * Handle domain renewal action.
     */
    public function handle_renew_action(int $domain_id, int $period): array;

    /**
     * Handle auto-renewal toggle.
     */
    public function handle_auto_renew_toggle(int $domain_id, bool $enabled): array;
}
```

### WooCommerce Integration Hooks

```php
// Add tab
add_filter( 'woocommerce_account_menu_items', array( $myaccount, 'add_menu_item' ) );

// Register endpoint
add_action( 'init', function() {
    add_rewrite_endpoint( 'my-domains', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'domain-details', EP_ROOT | EP_PAGES );
} );

// Add tab content
add_action( 'woocommerce_account_my-domains_endpoint', array( $myaccount, 'render_domains_list' ) );
add_action( 'woocommerce_account_domain-details_endpoint', array( $myaccount, 'render_domain_details' ) );

// Flush rewrite rules on activation
register_activation_hook( __FILE__, 'flush_rewrite_rules' );
```

### Domain Details Template Features

- Domain status badge (registered, pending, failed, expired)
- Registration date
- Expiry date with countdown
- Auto-renewal toggle
- Nameserver display
- Renew button (with period selector)
- Transfer eligibility indicator
- Order link (if domain linked to order)
- Actions: Renew, Update Nameservers, View DNS Records

---

## Implementation Priority & Timeline

| Feature | Effort (weeks) | Dependencies | Priority |
|---------|---------------|--------------|----------|
| 2.2 Renewal Service | 2 | v1 complete | 1 |
| 2.4 My Account Panel | 2 | v1 complete | 2 |
| 2.1 Transfer Workflow | 2 | None | 3 |
| 2.3 DNS Management | 3 | v1 complete | 4 |
| Renewal Notifications | 1 | 2.2 complete | 5 |

**Total Phase 2: 6-8 weeks**

---

## Testing Checklist for Phase 2

### Unit Tests
- [ ] TransferService all methods
- [ ] RenewalService all methods
- [ ] DnsService all methods
- [ ] RenewalNotifier scheduling and sending
- [ ] MyAccountDomains permission checks

### Integration Tests (wp-env)
- [ ] Transfer: search → cart → checkout → initiate
- [ ] Renewal: My Account → select period → cart → checkout → renew
- [ ] DNS: update nameservers → verify via API
- [ ] DNS: add/update/delete record → verify via API

### E2E Tests (Playwright)
- [ ] Complete transfer flow with auth code
- [ ] Renewal from My Account dashboard
- [ ] DNS management with all record types
- [ ] Auto-renewal toggle persistence

### Manual Testing
- [ ] Test with real OpenProvider sandbox account
- [ ] Verify email notifications for expiring domains
- [ ] Test nameserver propagation delay warnings
- [ ] Verify permissions (can't access other users' domains)

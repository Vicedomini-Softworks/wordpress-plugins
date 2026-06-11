<?php
/**
 * Renewal Notifier class for OpenProvider WooCommerce
 *
 * Sends domain expiry notification emails on a daily WP-Cron schedule.
 *
 * @package OpenProviderWooCommerce\WooCommerce
 */

namespace OpenProviderWooCommerce\WooCommerce;

use OpenProviderWooCommerce\Support\Settings;
use OpenProviderWooCommerce\Support\Logger;

/**
 * Renewal Notifier class.
 */
class RenewalNotifier {

	/**
	 * WP-Cron hook name.
	 */
	public const CRON_HOOK = 'opwc_check_expiring_domains';

	/**
	 * Notification thresholds in days before expiry.
	 *
	 * @var int[]
	 */
	private const THRESHOLDS = array( 30, 14, 7, 1 );

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Domain repository.
	 *
	 * @var DomainRepository
	 */
	private DomainRepository $repository;

	/**
	 * Notification repository.
	 *
	 * @var NotificationRepository
	 */
	private NotificationRepository $notifications;

	/**
	 * Constructor.
	 *
	 * @param Settings               $settings Settings.
	 * @param Logger                 $logger Logger.
	 * @param DomainRepository       $repository Domain repository.
	 * @param NotificationRepository $notifications Notification repository.
	 */
	public function __construct(
		Settings $settings,
		Logger $logger,
		DomainRepository $repository,
		NotificationRepository $notifications
	) {
		$this->settings      = $settings;
		$this->logger        = $logger;
		$this->repository    = $repository;
		$this->notifications = $notifications;
	}

	/**
	 * Register scheduled hook.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'check_expiring_domains' ) );
	}

	/**
	 * Check for expiring domains and send notifications.
	 *
	 * Called via WP Cron daily.
	 */
	public function check_expiring_domains(): void {
		foreach ( self::THRESHOLDS as $days ) {
			$type = "expiry_{$days}";

			foreach ( $this->repository->find_expiring_within( $days ) as $domain ) {
				if ( $this->notifications->has_been_sent( (int) $domain->id, $type ) ) {
					continue;
				}

				$expiry            = strtotime( $domain->expires_at );
				$days_until_expiry = (int) ceil( ( $expiry - time() ) / DAY_IN_SECONDS );

				$this->send_expiry_notification( $domain, $days_until_expiry );
				$this->mark_notification_sent( (int) $domain->id, $type );
			}
		}
	}

	/**
	 * Send expiry notification for a domain.
	 *
	 * @param object $domain Domain record.
	 * @param int    $days_until_expiry Days until expiry.
	 */
	private function send_expiry_notification( object $domain, int $days_until_expiry ): void {
		if ( ! $domain->customer_id ) {
			return;
		}

		$user = get_user_by( 'id', $domain->customer_id );

		if ( ! $user ) {
			return;
		}

		$domain_name = $domain->domain_name . '.' . $domain->tld;

		$subject = sprintf(
			/* translators: %1$s: domain name, %2$d: days until expiry */
			__( 'Your domain %1$s expires in %2$d day(s)', 'openprovider-woocommerce' ),
			$domain_name,
			$days_until_expiry
		);

		$message = sprintf(
			/* translators: %1$s: domain name, %2$d: days until expiry, %3$s: expiry date */
			__(
				'Your domain %1$s is set to expire in %2$d day(s) on %3$s.

%4$s

If you have auto-renewal enabled, no action is required.',
				'openprovider-woocommerce'
			),
			$domain_name,
			$days_until_expiry,
			date_i18n( get_option( 'date_format' ), strtotime( $domain->expires_at ) ),
			$domain->auto_renew
				? __( 'This domain will be renewed automatically.', 'openprovider-woocommerce' )
				: __( 'Please renew it from your account to avoid losing it.', 'openprovider-woocommerce' )
		);

		if ( class_exists( 'VS_Mailer' ) && method_exists( 'VS_Mailer', 'send' ) ) {
			\VS_Mailer::send( $user->user_email, $subject, $message );
		} else {
			wp_mail( $user->user_email, $subject, $message );
		}

		$this->logger->info( "Sent expiry notification ({$days_until_expiry} days) for domain {$domain_name} to {$user->user_email}" );
	}

	/**
	 * Mark notification as sent.
	 *
	 * @param int    $domain_id Domain ID.
	 * @param string $type Notification type.
	 */
	private function mark_notification_sent( int $domain_id, string $type ): void {
		$this->notifications->mark_sent( $domain_id, $type );
	}
}

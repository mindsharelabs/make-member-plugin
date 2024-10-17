<?php



class MAKE_notifications {

    public function __construct() {

        add_action('woocommerce_subscription_status_cancelled', array('MAKE_notifications', 'send_subscription_cancelled_email'), 10, 1);
        add_action('woocommerce_subscription_status_expired', array('MAKE_notifications', 'send_subscription_cancelled_email'), 10, 1);

        
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }



    static function send_subscription_cancelled_email($subscription_id) {
        //if woocommerce is active
        
        $subscription = new WC_Subscription($subscription_id);
        $user = get_userdata($subscription->get_user_id());
        $user_info = array(
            'email' => $user->user_email,
            'username' => $user->user_login,
            'ID' => $user->ID,
            'name' => $user->display_name
        );
    
        $to_email = 'build@makesantafe.org';
        // $to_email = 'james@makesantafe.org';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $subject = 'Subscription Cancelled - ' . $user_info['name'];

        $message = '<body>';

        $message .= '<h1>The following subscription has been canceled</h1></br>';
        $message .= 'Name: ' . $user_info['name'] . '<br>';
        $message .= 'Email: ' . $user_info['email'] . '<br>';
        $message .= 'User ID: ' . $user_info['ID'] . '<br>';
        $message .= '<strong><a href="' . $subscription->get_edit_order_url() . '" target="_blank">View Subscription</a></strong><br>';
        if($subscription->get_related_orders()) :
            $message .= 'Associated Orders:</br>';
            $message .= '<ul>';
            foreach ( $subscription->get_related_orders() as $order_id ) {

                $order = new WC_Order( $order_id );
                $message .= '<li>';
                    $message .= 'Order ID: <a href="' . $order->get_edit_order_url() . '" target="_blank">' . $order_id . '</a>';
                    $message .= '<ul>';
                    $items = $order->get_items();
                    foreach ( $items as $product ) {
                        $message .= '<li>' . $product['name'] . '</li>';
                    }
                    $message .= '</ul>';
                $message .= '</li>';
            }
            $message .= '</ul>';
        endif;
        $message .= '</body>';

        wp_mail($to_email, $subject, $message, $headers);
    }
    
        
}

new MAKE_notifications();

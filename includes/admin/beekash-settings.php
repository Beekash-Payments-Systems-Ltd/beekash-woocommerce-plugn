<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'enabled'         => array(
        'title'       => __('Enable/Disable', 'beekash-payment'),
        'label'       => __('Enable Beekash', 'beekash-payment'),
        'type'        => 'select',
        'default'     => 'no',
        'desc_tip'    => true,
        'options'     => array(
            'yes'       => __('Yes', 'beekash-payment'),
            'no' => __('No', 'beekash-payment')
        ),
    ),
    'title'            => array(
        'title'       => __('Title', 'beekash-payment'),
        'type'        => 'text',
        'default'     => __('Beekash', 'beekash-payment'),
        'desc_tip'    => true,
    ),
    'description'    => array(
        'title'       => __('Description', 'beekash-payment'),
        'type'        => 'textarea',
        'default'     => __('Seamless payment with Card, Mobile Money', 'beekash-payment'),
        'desc_tip'    => true,
    ),
    'publish_key'     => array(
        'title'       => __('Publish Key', 'beekash-payment'),
        'type'        => 'text',
        'default'     => '',
    ),
    'meta_products'   => array(
        'title'       => __('Product(s) Orders', 'beekash-payment'),
        'label'       => __('Send Product(s) Ordered', 'beekash-payment'),
        'type'        => 'select',
        'description' => __('If checked, the product(s) paid for  will be sent to Beekash', 'beekash-payment'),
        'default'     => 'yes',
        'desc_tip'    => true,
        'options'     => array(
            'true'       => __('Yes', 'beekash-payment'),
            'false' => __('No', 'beekash-payment')
        ),
    ),
    'auto_complete'                         => array(
        'title'       => __( 'Auto Complete', 'beekash-payment' ),
        'label'       => __( 'Auto Complete successful order', 'beekash-payment' ),
        'type'        => 'checkbox',
        'description' => __( 'If checked, automatically update order status to Completed after a successful payment.', 'beekash-payment' ),
        'default'     => 'no',
        'desc_tip'    => true,
    ),
);

// OLD WITH VOUCHERKARD
// return array(
//     'enabled'         => array(
//         'title'       => __('Enable/Disable', 'beekash-payment'),
//         'label'       => __('Enable Beekash', 'beekash-payment'),
//         'type'        => 'select',
//         'default'     => 'no',
//         'desc_tip'    => true,
//         'options'     => array(
//             'yes'       => __('Yes', 'beekash-payment'),
//             'no' => __('No', 'beekash-payment')
//         ),
//     ),
//     'title'            => array(
//         'title'       => __('Title', 'beekash-payment'),
//         'type'        => 'text',
//         'default'     => __('Beekash', 'beekash-payment'),
//         'desc_tip'    => true,
//     ),
//     'description'    => array(
//         'title'       => __('Description', 'beekash-payment'),
//         'type'        => 'textarea',
//         'default'     => __('Seamless payment with Card, Mobile Money', 'beekash-payment'),
//         'desc_tip'    => true,
//     ),
//     'publish_key'     => array(
//         'title'       => __('Publish Key', 'beekash-payment'),
//         'type'        => 'text',
//         'default'     => '',
//     ),
//     'voucherkard_id'      => array(
//         'title'       => __('VoucherKard ID', 'beekash-payment'),
//         'type'        => 'text',
//         'default'     => '',
//     ),
//     'meta_products'   => array(
//         'title'       => __('Product(s) Orders', 'beekash-payment'),
//         'label'       => __('Send Product(s) Ordered', 'beekash-payment'),
//         'type'        => 'select',
//         'description' => __('If checked, the product(s) paid for  will be sent to Beekash', 'beekash-payment'),
//         'default'     => 'yes',
//         'desc_tip'    => true,
//         'options'     => array(
//             'true'       => __('Yes', 'beekash-payment'),
//             'false' => __('No', 'beekash-payment')
//         ),
//     ),
//     'auto_complete'                         => array(
//         'title'       => __( 'Auto Complete', 'beekash-payment' ),
//         'label'       => __( 'Auto Complete successful order', 'beekash-payment' ),
//         'type'        => 'checkbox',
//         'description' => __( 'If checked, automatically update order status to Completed after a successful payment.', 'beekash-payment' ),
//         'default'     => 'no',
//         'desc_tip'    => true,
//     ),
// );
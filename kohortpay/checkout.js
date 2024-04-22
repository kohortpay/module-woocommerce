const settings = window.wc.wcSettings.getSetting( 'kohortpay', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'Kohortpay : Pay, refer & save money', 'kohortpay' );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description ) || window.wp.i18n.__( 'Save money and so does your friend, from the first friend you invite.', 'kohortpay' );
};
const Block_Gateway = {
    name: 'kohortpay',
    label: label,
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );
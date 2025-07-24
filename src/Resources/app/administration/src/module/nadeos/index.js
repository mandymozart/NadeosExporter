// <plugin root>/src/Resources/app/administration/src/module/swag-example/index.js
// import './page/provision';
// import './page/swag-example-detail';
// import './page/swag-example-create';
// import deDE from './snippet/de-DE';
// import enGB from './snippet/en-GB';

Shopware.Module.register('nadeos-provision', {
    type: 'plugin',
    name: 'nadeos-provision.general.mainMenuItemGeneral',
    title: 'nadeos-provision.general.mainMenuItemGeneral',
    description: 'sw-property.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',

    snippets: {
        'de-DE': {
            "nadeos-provision": {
                "general": {
                    "mainMenuItemGeneral": "Nadeos Exporter",
                    "descriptionTextModule": "Manage this custom module here"
                }
            }
        }, // deDE,
        'en-GB': {
            "nadeos-provision": {
                "general": {
                    "mainMenuItemGeneral": "Nadeos Exporter",
                    "descriptionTextModule": "Manage this custom module here"
                }
            }
        }, // enGB
    },

    routes: {
        // list: {
        //     component: 'swag-example-list',
        //     path: 'list'
        // },
        // detail: {
        //     component: 'swag-example-detail',
        //     path: 'detail/:id',
        //     meta: {
        //         parentPath: 'swag.example.list'
        //     }
        // },
        // create: {
        //     component: 'swag-example-create',
        //     path: 'create',
        //     meta: {
        //         parentPath: 'swag.example.list'
        //     }
        // }
    },

    navigation: [{
        label: 'nadeos-provision.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'nadeos-provision.provision',
        icon: 'default-shopping-paper-bag-product',
        parent: 'sw-catalogue',
        position: 100
    }]
});

import { MenuItem, Widget, ExtensionComponent } from '@fleetbase/ember-core/contracts';

export default {
    setupExtension(app, universe) {
        const menuService = universe.getService('menu');
        const registryService = universe.getService('registry');
        const widgetService = universe.getService('widget');
        const intlService = app.lookup('service:intl');

        // Register header navigation
        menuService.registerHeaderMenuItem('Fleet-Ops', 'console.fleet-ops', {
            icon: 'route',
            priority: 0,
            description: intlService.t('fleetops-ui.js.extension.dispatch-fleet-management-driver-tracking-and'),
            shortcuts: [
                {
                    title: intlService.t('fleetops-ui.js.extension.radar'),
                    description: intlService.t('fleetops-ui.js.extension.everything-across-resources-maintenance-and-staffing'),
                    icon: 'satellite-dish',
                    route: 'console.fleet-ops.management.index',
                    permission: 'fleet-ops list driver',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.orders'),
                    description: intlService.t('fleetops-ui.js.extension.create-dispatch-and-track-delivery-orders'),
                    icon: 'boxes-stacked',
                    route: 'console.fleet-ops.operations.orders',
                    permission: 'fleet-ops list order',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.places'),
                    description: intlService.t('fleetops-ui.js.extension.manage-saved-locations-addresses-and-points'),
                    icon: 'location-dot',
                    route: 'console.fleet-ops.management.places',
                    permission: 'fleet-ops list place',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.drivers'),
                    description: intlService.t('fleetops-ui.js.extension.manage-driver-profiles-assignments-and-live'),
                    icon: 'id-card',
                    route: 'console.fleet-ops.management.drivers',
                    permission: 'fleet-ops list driver',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.vehicles'),
                    description: intlService.t('fleetops-ui.js.extension.view-and-manage-your-vehicle-fleet'),
                    icon: 'truck',
                    route: 'console.fleet-ops.management.vehicles',
                    permission: 'fleet-ops list vehicle',
                },
                {
                    title: intlService.t('menu.trailers'),
                    description: intlService.t('trailer.navigation-description'),
                    icon: 'trailer',
                    route: 'console.fleet-ops.management.trailers',
                    permission: 'fleet-ops list trailer',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.fleets'),
                    description: intlService.t('fleetops-ui.js.extension.organise-drivers-and-vehicles-into-operational'),
                    icon: 'layer-group',
                    route: 'console.fleet-ops.management.fleets',
                    permission: 'fleet-ops list fleet',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.service-rates'),
                    description: intlService.t('fleetops-ui.js.extension.configure-pricing-rules-and-service-rate'),
                    icon: 'tags',
                    route: 'console.fleet-ops.operations.service-rates',
                    permission: 'fleet-ops list service-rate',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.devices'),
                    description: intlService.t('fleetops-ui.js.extension.manage-connected-telematics-devices-and-their'),
                    icon: 'microchip',
                    route: 'console.fleet-ops.connectivity.devices',
                    permission: 'fleet-ops list device',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.reports'),
                    description: intlService.t('fleetops-ui.js.extension.generate-and-review-operational-analytics-reports'),
                    icon: 'chart-bar',
                    route: 'console.fleet-ops.analytics.reports',
                    permission: 'iam list report',
                },
                {
                    title: intlService.t('fleetops-ui.js.extension.orchestrator'),
                    description: intlService.t('fleetops-ui.js.extension.intelligently-allocate-and-dispatch-orders-to'),
                    icon: 'diagram-project',
                    route: 'console.fleet-ops.operations.orchestrator',
                    permission: 'fleet-ops list order',
                },
            ],
        });

        // Register admin sections
        menuService.registerAdminMenuPanel(
            'Fleet-Ops',
            [
                new MenuItem({
                    slug: 'routing',
                    title: intlService.t('fleetops-ui.js.extension.routing'),
                    icon: 'route',
                    component: new ExtensionComponent('@fleetbase/fleetops-engine', 'admin/routing-settings'),
                }),
                new MenuItem({
                    slug: 'map',
                    title: intlService.t('fleetops-ui.js.extension.map'),
                    icon: 'map',
                    component: new ExtensionComponent('@fleetbase/fleetops-engine', 'admin/map-settings'),
                }),
                new MenuItem({
                    slug: 'navigator-app',
                    title: intlService.t('fleetops-ui.js.extension.navigator-app'),
                    icon: 'location-arrow',
                    component: new ExtensionComponent('@fleetbase/fleetops-engine', 'admin/navigator-app'),
                }),
                new MenuItem({
                    slug: 'telematics',
                    title: intlService.t('fleetops-ui.js.extension.telematics'),
                    icon: 'satellite-dish',
                    component: new ExtensionComponent('@fleetbase/fleetops-engine', 'admin/telematics-settings'),
                }),
                new MenuItem({
                    slug: 'tracking-page',
                    title: intlService.t('fleetops-ui.js.extension.tracking-page'),
                    icon: 'magnifying-glass-location',
                    component: new ExtensionComponent('@fleetbase/fleetops-engine', 'admin/tracking-page-settings'),
                }),
            ],
            {
                slug: 'fleet-ops',
            }
        );

        // Links printed or emailed without the public `~/` prefix (/track-order?order=...) land
        // inside the console. Signed-out visitors are sent on to the public route after login;
        // registering the page in the console too shows it to signed-in staff instead of an
        // empty console page.
        for (const slug of ['track', 'track-order']) {
            menuService.registerMenuItem(
                'console',
                new MenuItem({
                    title: intlService.t('fleetops-ui.js.extension.track-order'),
                    slug,
                    section: null,
                    view: null,
                    component: new ExtensionComponent('@fleetbase/fleetops-engine', 'tracking-page'),
                })
            );
        }

        // Register track order button
        menuService.registerMenuItem(
            'auth:login',
            new MenuItem({
                title: intlService.t('fleetops-ui.js.extension.track-order'),
                route: 'virtual',
                slug: 'track-order',
                icon: 'barcode',
                type: 'default',
                // btn-auth (ember-ui): the sign-in page's neutral button, matching the
                // console's "Continue with ..." provider buttons, hover included.
                wrapperClass: 'btn-block btn-auth',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'tracking-page'),
                onClick: (menuItem) => {
                    universe.transitionMenuItem('virtual', menuItem);
                },
            })
        );

        // The customer tracking page: /~/track/{number} is the shared page.
        menuService.registerMenuItem(
            'auth:login',
            new MenuItem({
                title: intlService.t('fleetops-ui.js.extension.track'),
                route: 'virtual',
                slug: 'track',
                type: 'link',
                wrapperClass: 'hidden',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'tracking-page'),
                onClick: (menuItem) => {
                    universe.transitionMenuItem('virtual', menuItem);
                },
            })
        );

        // A company's own tracking page: /~/t/{slug}/{number}.
        menuService.registerMenuItem(
            'auth:login',
            new MenuItem({
                title: intlService.t('fleetops-ui.js.extension.company-tracking'),
                route: 'virtual',
                slug: 't',
                type: 'link',
                wrapperClass: 'hidden',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'tracking-page'),
                onClick: (menuItem) => {
                    universe.transitionMenuItem('virtual', menuItem);
                },
            })
        );

        menuService.registerMenuItem(
            'auth:login',
            new MenuItem({
                title: intlService.t('fleetops-ui.js.extension.inspection'),
                route: 'virtual',
                slug: 'inspection',
                type: 'link',
                wrapperClass: 'hidden',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'public-inspection'),
                onClick: (menuItem) => {
                    universe.transitionMenuItem('virtual', menuItem);
                },
            })
        );

        // Register widgets
        this.registerWidgets(widgetService, intlService);

        // Create registries
        this.createRegistries(registryService);

        // Declare the registries extensions can add table columns, actions and
        // buttons to, e.g. `fleet-ops:driver:table:columns` or `fleet-ops:order:details:menu`.
        universe
            .getService('universe/resource-view-service')
            ?.declare('fleet-ops', [
                'contact',
                'customer',
                'device',
                'device-event',
                'driver',
                'equipment',
                'fleet',
                'fuel-report',
                'fuel-transaction',
                'inspection-form',
                'inspection-submission',
                'integrated-vendor',
                'issue',
                'maintenance',
                'order',
                'part',
                'place',
                'report',
                'schedule',
                'sensor',
                'service-rate',
                'trailer',
                'vehicle',
                'vendor',
                'work-order',
            ]);
        registryService.registerRenderableComponent('ai:action-preview:fleet-ops.create_order', new ExtensionComponent('@fleetbase/fleetops-engine', 'ai/create-order-preview'));

        // // Register console home guidance
        // this.registerHomeComponents(registryService);

        // Setup customer portal
        const isCustomerPortalInstalled = universe.extensionManager.isInstalled('@fleetbase/customer-portal-engine');
        if (isCustomerPortalInstalled) {
            universe.whenEngineLoaded('@fleetbase/customer-portal-engine', () => {
                universe.extensionManager.ensureEngineLoaded('@fleetbase/fleetops-engine');
            });
        }
    },

    registerWidgets(widgetService, intlService = { t: (key) => key }) {
        // `order` places a default widget on the default dashboard, which mixes widgets from
        // every extension; the grid fills rows first-fit, lowest order first (widgets without
        // one follow). The default layout is:
        //   10-40    KPI row: Radar, Revenue (ledger, 20), Active Orders, Drivers Online
        //   41-44    Ledger KPI row: Expenses, Net Income, Outstanding AR, Overdue AR
        //   50       Live Fleet Map, full width
        //   60-80    Revenue Trend, Top Drivers, Maintenance Overview, a third of the width each
        //   150-170  Ledger Recent Financial Activity + Cash Flow Summary | console Blog + GitHub
        const widgets = [
            // Legacy monolithic 13-tile widget — kept registered for one release as
            // users have it pinned to existing dashboards. The new KPI tile widgets
            // below supersede it. Scheduled for removal in the next major.
            new Widget({
                id: 'fleet-ops-key-metrics-widget',
                name: intlService.t('fleetops-ui.js.extension.fleet-ops-metrics-legacy'),
                description: intlService.t('fleetops-ui.js.extension.deprecated-replaced-by-individual-kpi-tile'),
                icon: 'truck',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/fleet-ops-key-metrics'),
                grid_options: { w: 12, h: 8, minW: 8, minH: 6 },
                category: 'Legacy',
                default: false,
            }),

            // Small KPI tile widgets — S-tier, each wraps <Widget::KpiTile> with a
            // fixed slug pointing at GET /fleet-ops/metrics/{slug}.
            new Widget({
                id: 'fleet-ops-kpi-earnings-widget',
                name: intlService.t('fleetops-ui.js.extension.earnings-30d'),
                description: intlService.t('fleetops-ui.js.extension.total-earnings-over-the-last-30'),
                icon: 'sack-dollar',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/kpi-earnings'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'KPI Tiles',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-kpi-aov-widget',
                name: intlService.t('fleetops-ui.js.extension.avg-order-value-30d'),
                description: intlService.t('fleetops-ui.js.extension.average-revenue-per-completed-order-with'),
                icon: 'receipt',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/kpi-aov'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'KPI Tiles',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-kpi-distance-widget',
                name: intlService.t('fleetops-ui.js.extension.distance-travelled-30d'),
                description: intlService.t('fleetops-ui.js.extension.total-kilometres-delivered-over-the-last'),
                icon: 'route',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/kpi-distance'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'KPI Tiles',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-kpi-active-orders-widget',
                name: intlService.t('fleetops-ui.js.extension.active-orders'),
                description: intlService.t('fleetops-ui.js.extension.live-count-of-orders-currently-in'),
                icon: 'bolt',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/kpi-active-orders'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'KPI Tiles',
                order: 30,
                default: true,
            }),
            new Widget({
                id: 'fleet-ops-kpi-drivers-online-widget',
                name: intlService.t('fleetops-ui.js.extension.drivers-online'),
                description: intlService.t('fleetops-ui.js.extension.live-count-of-drivers-currently-active'),
                icon: 'id-card',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/kpi-drivers-online'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'KPI Tiles',
                order: 40,
                default: true,
            }),
            new Widget({
                id: 'fleet-ops-radar-widget',
                name: intlService.t('fleetops-ui.js.extension.radar'),
                description: intlService.t('fleetops-ui.js.extension.what-needs-a-decision-across-the'),
                icon: 'satellite-dish',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/radar'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'KPI Tiles',
                order: 10,
                default: true,
            }),
            new Widget({
                id: 'fleet-ops-kpi-open-issues-widget',
                name: intlService.t('fleetops-ui.js.extension.open-issues'),
                description: intlService.t('fleetops-ui.js.extension.pending-issues-across-the-fleet-lower'),
                icon: 'triangle-exclamation',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/kpi-open-issues'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'KPI Tiles',
                default: false,
            }),

            // Analytics widgets — composed visualizations powered by /fleet-ops/analytics/*.
            new Widget({
                id: 'fleet-ops-operations-pulse-widget',
                name: intlService.t('fleetops-ui.js.extension.operations-pulse'),
                description: intlService.t('fleetops-ui.js.extension.live-operational-snapshot-with-day-over'),
                icon: 'wave-pulse',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/operations-pulse'),
                grid_options: { w: 6, h: 6, minW: 5, minH: 5 },
                category: 'Analytics',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-live-fleet-widget',
                name: intlService.t('fleetops-ui.js.extension.live-fleet-map'),
                description: intlService.t('fleetops-ui.js.extension.real-time-driver-positions-and-active'),
                icon: 'map-location-dot',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/live-fleet'),
                grid_options: { w: 12, h: 12, minW: 8, minH: 8 },
                category: 'Maps',
                order: 50,
                default: true,
            }),
            new Widget({
                id: 'fleet-ops-revenue-trend-widget',
                name: intlService.t('fleetops-ui.js.extension.revenue-trend'),
                description: intlService.t('fleetops-ui.js.extension.revenue-over-time-with-period-comparison'),
                icon: 'chart-line',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/revenue-trend'),
                grid_options: { w: 4, h: 9, minW: 4, minH: 7 },
                category: 'Analytics',
                order: 60,
                default: true,
            }),
            new Widget({
                id: 'fleet-ops-orders-by-status-widget',
                name: intlService.t('fleetops-ui.js.extension.order-volume-by-status'),
                description: intlService.t('fleetops-ui.js.extension.daily-stacked-bars-of-order-counts'),
                icon: 'chart-column',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/orders-by-status'),
                grid_options: { w: 6, h: 6, minW: 5, minH: 5 },
                category: 'Analytics',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-on-time-delivery-widget',
                name: intlService.t('fleetops-ui.js.extension.on-time-delivery'),
                description: intlService.t('fleetops-ui.js.extension.percentage-of-deliveries-completed-within-the'),
                icon: 'clock',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/on-time-delivery'),
                grid_options: { w: 3, h: 4, minW: 3, minH: 4 },
                category: 'Analytics',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-top-drivers-widget',
                name: intlService.t('fleetops-ui.js.extension.top-drivers'),
                description: intlService.t('fleetops-ui.js.extension.driver-leaderboard-sortable-by-orders-on'),
                icon: 'medal',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/top-drivers'),
                grid_options: { w: 4, h: 9, minW: 4, minH: 6 },
                category: 'Analytics',
                order: 70,
                default: true,
            }),
            new Widget({
                id: 'fleet-ops-fuel-efficiency-widget',
                name: intlService.t('fleetops-ui.js.extension.fuel-cost-efficiency'),
                description: intlService.t('fleetops-ui.js.extension.weekly-fuel-cost-and-cost-per'),
                icon: 'gas-pump',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/fuel-efficiency'),
                grid_options: { w: 6, h: 6, minW: 5, minH: 5 },
                category: 'Analytics',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-fuel-providers-widget',
                name: intlService.t('fleetops-ui.js.extension.fuel-integrations'),
                description: intlService.t('fleetops-ui.js.extension.provider-spend-unmatched-transactions-and-sync'),
                icon: 'gas-pump',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/fuel-providers'),
                grid_options: { w: 4, h: 5, minW: 4, minH: 5 },
                category: 'Analytics',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-issues-insights-widget',
                name: intlService.t('fleetops-ui.js.extension.issues-insights'),
                description: intlService.t('fleetops-ui.js.extension.open-resolved-issues-category-breakdown-and'),
                icon: 'triangle-exclamation',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/issues-insights'),
                grid_options: { w: 6, h: 6, minW: 5, minH: 5 },
                category: 'Analytics',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-maintenance-overview-widget',
                name: intlService.t('fleetops-ui.js.extension.maintenance-overview'),
                description: intlService.t('fleetops-ui.js.extension.overdue-scheduled-and-ytd-maintenance-spend'),
                icon: 'wrench',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/maintenance-overview'),
                grid_options: { w: 4, h: 9, minW: 4, minH: 6 },
                category: 'Analytics',
                order: 80,
                default: true,
            }),
            new Widget({
                id: 'fleet-ops-proof-of-delivery-widget',
                name: intlService.t('fleetops-ui.js.extension.proof-of-delivery'),
                description: intlService.t('fleetops-ui.js.extension.the-latest-proof-of-delivery-photos'),
                icon: 'camera',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/proof-of-delivery'),
                grid_options: { w: 4, h: 9, minW: 3, minH: 6 },
                category: 'Operations',
                default: false,
            }),
            new Widget({
                id: 'fleet-ops-geofence-violations-widget',
                name: intlService.t('fleetops-ui.js.extension.geofence-violations'),
                description: intlService.t('fleetops-ui.js.extension.dwell-time-outliers-and-geofence-event'),
                icon: 'location-crosshairs',
                component: new ExtensionComponent('@fleetbase/fleetops-engine', 'widget/geofence-violations'),
                grid_options: { w: 6, h: 6, minW: 5, minH: 5 },
                category: 'Analytics',
                default: false,
            }),
        ];

        const defaultWidgets = widgets
            .filter((widget) => (typeof widget.isDefault === 'function' ? widget.isDefault() : widget.default === true))
            .map((widget) => {
                const definition = typeof widget.toObject === 'function' ? widget.toObject() : widget;

                return {
                    ...definition,
                    widgetId: definition.widgetId ?? definition.id,
                };
            });

        widgetService.registerWidgets('dashboard', widgets);

        if (typeof widgetService.registerDashboard === 'function') {
            widgetService.registerDashboard('fleet-ops');
        }

        widgetService.registerWidgets('fleet-ops', widgets);

        if (typeof widgetService.registerDefaultWidgets === 'function') {
            widgetService.registerDefaultWidgets('fleet-ops', defaultWidgets);
        }
    },

    registerHomeComponents(registryService) {
        registryService.registerRenderableComponent('console:home:before-dashboard', new ExtensionComponent('@fleetbase/fleetops-engine', 'home/getting-started-guidance'));
    },

    createRegistries(registryService) {
        registryService.createRegistries([
            'engine:fleet-ops',
            'fleet-ops:component:map:drawer',
            'fleet-ops:component:vehicle:details',
            'fleet-ops:component:driver:details',
            'fleet-ops:component:order-config-manager',
            'fleet-ops:component:contact:form',
            'fleet-ops:component:contact:form:details',
            'fleet-ops:component:customer:form',
            'fleet-ops:component:customer:form:details',
            'fleet-ops:component:driver:form',
            'fleet-ops:component:driver:form:details',
            'fleet-ops:component:fleet:form',
            'fleet-ops:component:fleet:form:details',
            'fleet-ops:component:place:form',
            'fleet-ops:component:place:form:details',
            'fleet-ops:component:vehicle:form',
            'fleet-ops:component:vehicle:form:details',
            'fleet-ops:component:vehicle:form:after-details',
            'fleet-ops:component:vehicle:form:start',
            'fleet-ops:component:vehicle:form:end',
            'fleet-ops:component:vehicle:card:header:start',
            'fleet-ops:component:vehicle:card:header:end',
            'fleet-ops:component:vehicle:card:footer:start',
            'fleet-ops:component:vehicle:card:footer:end',
            'fleet-ops:component:vendor:form:edit',
            'fleet-ops:component:vendor:form:edit:details',
            'fleet-ops:component:vendor:form:create',
            'fleet-ops:component:vendor:form:create:details',
            'fleet-ops:component:issue:form',
            'fleet-ops:component:issue:form:details',
            'fleet-ops:component:fuel-report:form',
            'fleet-ops:component:fuel-report:form:details',
            'fleet-ops:component:maintenance:form',
            'fleet-ops:component:maintenance:form:details',
            'fleet-ops:component:maintenance:details',
            'fleet-ops:component:inspection-form:form',
            'fleet-ops:component:inspection-form:details',
            'fleet-ops:component:inspection-submission:form',
            'fleet-ops:component:inspection-submission:details',
            'fleet-ops:component:public-inspection',
            'fleet-ops:component:work-order:form',
            'fleet-ops:component:work-order:form:details',
            'fleet-ops:component:work-order:details',
            'fleet-ops:component:equipment:form',
            'fleet-ops:component:equipment:form:details',
            'fleet-ops:component:equipment:details',
            'fleet-ops:component:part:form',
            'fleet-ops:component:part:form:details',
            'fleet-ops:component:part:details',
            'fleet-ops:contextmenu:vehicle',
            'fleet-ops:contextmenu:driver',
            'fleet-ops:component:order:details',
            'fleet-ops:component:order:form',
            'fleet-ops:component:order:form:start',
            'fleet-ops:component:order:form:end',
            'fleet-ops:component:order:form:details',
            'fleet-ops:component:order:form:details:start',
            'fleet-ops:component:order:form:details:end',
            'fleet-ops:component:order:form:details:after-details',
            'fleet-ops:component:order:form:payload:entity',
            'fleet-ops:component:order:form:payload:entity:form',
            'fleet-ops:template:settings:routing',
            'fleet-ops:template:settings:orchestrator',
            'fleet-ops:component:admin:routing-settings',
            'fleet-ops:component:admin:telematics-settings',
        ]);
    },
};

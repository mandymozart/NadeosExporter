// Import all necessary Storefront plugins
import NadeosExporter from './nadeos-exporter/nadeos-exporter.plugin';

// Register your plugin via the existing PluginManager
const PluginManager = window.PluginManager;

PluginManager.register('NadeosExporter', NadeosExporter, '[data-nadeos-exporter]');

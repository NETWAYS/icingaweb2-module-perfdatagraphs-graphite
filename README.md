**Note:** This is an early release that is still in development and prone to change

# Icinga Web Performance Data Graphs Graphite Backend

A Graphite backend for the Icinga Web Performance Data Graphs Module.

## Known Issues

### Loads only limited amount of metrics

Loading a lot of data can cause the PHP memory exhaustion. Thus
the module currently only loads 10 metrics by default.
Use the custom variables `perfdatagraphs_config_metrics_include/exclude`
to select the metrics you need.

### Special chars in host or service name

Graphite does not work well with special characters.
The host and service name should use Latin characters, numbers, underscore or dash.
You can still use use `display_name` for the object.

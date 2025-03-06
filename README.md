# Icinga Web Performance Data Graphs Graphite Backend

A Graphite backend for the Icinga Web Performance Data Graphs Module.

## Known Issues

### Special chars in host or service name

Graphite does not work well with special characters.
The host and service name should use Latin characters, numbers, underscore or dash.
You can still use use `display_name` for the object.

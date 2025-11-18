# Icinga Web Performance Data Graphs Graphite Backend

A Graphite backend for the Icinga Web Performance Data Graphs Module.

This module requires the frontend module:

- https://github.com/NETWAYS/icingaweb2-module-perfdatagraphs

## Installation Requirements

* PHP version ≥ 8.0
* IcingaDB or IDO Database
* A Graphite compatible API to fetch the data from (Graphite, carbonapi, VictoriaMetrics, etc.)

## Known Issues

### Special chars in host or service name

Graphite does not work well with special characters.
The host and service name should use Latin characters, numbers, underscore or dash.
You can still use use `display_name` for the object.

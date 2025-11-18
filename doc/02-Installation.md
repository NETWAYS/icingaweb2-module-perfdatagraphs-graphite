# Installation

## Packages

NETWAYS provides this module via [https://packages.netways.de](https://packages.netways.de/).

To install this module, follow the setup instructions for the **extras** repository.

**RHEL or compatible:**

`dnf install icingaweb2-module-perfdatagraphs-graphite`

**Ubuntu/Debian:**

`apt install icingaweb2-module-perfdatagraphs-graphite`

## From source

1. Clone a Icinga Web Performance Data Graphs Backend repository into `/usr/share/icingaweb2/modules/perfdatagraphsgraphite/`

2. Enable the module using the `Configuration → Modules` menu or the `icingacli`

3. Configure the Graphite URL and authentication using the `Configuration → Modules` menu

# Configuration

| Option  | Description | Default value  |
|---------|-------------|----------------|
| graphite_api_url                       | The URL for Graphite including the scheme                                                     | `http://localhost:8081`                                               |
| graphite_api_username                  | The user for HTTP basic auth. Not used if empty                                               |                                                                       |
| graphite_api_password                  | The password for HTTP basic auth. Not used if empty                                           |                                                                       |
| graphite_api_timeout                   | HTTP timeout for the API in seconds. Should be higher than 0                                  | `10` (seconds)                                                        |
| graphite_max_data_points               | The maximum numbers of datapoints each series returns. Should be higher than 0                | `10000`                                                               |
| graphite_api_tls_insecure              | Skip the TLS verification                                                                     | `false` (unchecked)                                                   |
| graphite_writer_host_name_template     | The value of your Icinga 2 GraphiteWriter's attribute `host_name_template` (if specified)     | `icinga2.$host.name$.host.$host.check_command$`                       |
| graphite_writer_service_name_template  | The value of your Icinga 2 GraphiteWriter's attribute `service_name_template` (if specified)  | `icinga2.$host.name$.services.$service.name$.$service.check_command$` |

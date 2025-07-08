# Installation

## Packages

To install the module via package manager, follow the setup instructions for the **extras** repo from [packages.netways.de](https://packages.netways.de/).

Afterwards you can install the package on these supported systems.

**RHEL or compatible:**

`dnf install icingaweb2-module-perfdatagraphs-influxdbv1`

**Ubuntu/Debian:**

`apt install icingaweb2-module-perfdatagraphs-influxdbv1`


## From source

1. Clone a Icinga Web Performance Data Graphs Backend repository into `/usr/share/icingaweb2/modules/perfdatagraphsgraphite/`

2. Enable the module using the `Configuration → Modules` menu or the `icingacli`

3. Configure the Graphite URL and authentication using the `Configuration → Modules` menu

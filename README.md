# OpenAuth.dev Provider for WoltLab Suite

<div align=center>

![openauth-icon](https://user-images.githubusercontent.com/81188/87538212-25d2ef00-c69c-11ea-87a7-b967826cb669.png)


### OpenAuth.dev Provider for WoltLab Suite

</div>

---

### Table of contents

* [About the project](#about-the-project)
* [Getting Started](#getting-started)
* [Configuration](#configuration)
* [Contributing](#contributing)
* [Versioning](#versioning)
* [Authors](#authors)
* [License](#license)

## About the project

WIP

## Prerequisites

You need:

- A WoltLab Suite installation (5.4.0 or newer)
- PHP (7.2.24 or newer)
- Activated `fsockopen` (including support for SSL connections) on your webspace/server
- A free user account on [OpenAuth.dev](https://www.openauth.dev), which has been authorized as a developer

## Getting started

Download the latest release from the [releases section](https://github.com/openauth-dev/dev.openauth.wsc.login/releases) and upload it in your WoltLab Suite installation.

That's it!

## Configuration

Common to all vendors is that you have to create an "application" for the respective vendor, and get an ID and secret key, which must be entered into the settings (Administration > Configuration > Options > User > Registration) of your community.

To obtain a key pair from OpenAuth.dev, you need to [create an application](https://www.openauth.dev/developer/app-create/) first. After successful creation, find your newly created application in the list of [your applications](https://www.openauth.dev/developer/my-apps/) and click the "Edit" button. At the bottom of that page, you'll find the Client ID and the corresponding Client Secret. Copy both and paste them into your WoltLab Suite settings.

Under normal circumstances, you should now be able to register/log in using OpenAuth.dev.

## Contributing

There are many ways to help this open source project. Write tutorials, improve documentation, share bugs with others, make feature requests, or just write code. We look forward to every contribution.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For available versions, see the [tags on this repository](https://github.com/openauth-dev/dev.openauth.wsc.login/tags).

## Authors

* **Peter Lohse** - *Main development* - [Hanashi](https://github.com/Hanashi)
* **Sascha Greuel** - *Main development* - [SoftCreatR](https://github.com/SoftCreatR)

See also the list of [contributors](https://github.com/openauth-dev/dev.openauth.wsc.login/graphs/contributors) who participated in this project.

## License

This project is licensed under the LGPL-2.1 License - see the [LICENSE](LICENSE) file for details.

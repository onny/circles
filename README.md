# Nextcloud Circles


### Important Notes 

 - `master` contains a version of Circles for NC22 and is not compatible with older version of Nextcloud_
 - Because of the huge difference between the last version and version prior to 22.0.0, any bugfixes about Circles for NC21 and older version should be applied to stable21.


### Bring cloud-users closer together

Circles allow your users to create their own groups of users/colleagues/friends. 
Those groups of users (or circles) can then be used by any other app for sharing purpose 
 through the Circles API


***

# API (PHP & Javascript) (Deprecated since NC22)

[Please visit our wiki to read more about the API.](https://github.com/nextcloud/circles/wiki)

# Installation

The app is distributed through the [app store](https://apps.nextcloud.com/apps/circles) and you can install it [right from your Nextcloud installation](https://docs.nextcloud.com/server/stable/admin_manual/apps_management.html).

Release tarballs are hosted at https://github.com/nextcloud-releases/mail/releases.

## Development setup

Just clone this repo into your apps directory ([Nextcloud server](https://github.com/nextcloud/server#running-master-checkouts) installation needed). Additionally, [npm](https://www.npmjs.com/) to fetch [Node.js](https://nodejs.org/en/download/package-manager/) is needed for installing JavaScript dependencies
and [composer](https://getcomposer.org/download/) is needed for dependency management in PHP.

Once npm and Node.js are installed, PHP dependencies can be installed by running:

```bash
composer i
occ app:enable circles
```
Make sure, that you are using a right path to ```occ```. This is located in _nextcloud/_ directory. See more [Using the occ command](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html#using-the-occ-command).

JavaScript dependencies can be installed by running:

```bash
# build for dev and watch changes
npm run watch
```

# Documentation

(to be written)


# Credits

App Icon by [Madebyoliver](http://www.flaticon.com/authors/madebyoliver) under Creative Commons BY 3.0

## Diglin Github ##
Magento module to allow users authenticate from Github account to your shop

## Features ##

- Account creation from Github account information: registration page is displayed to the user the first time he does a log in, pre-filled with the user public information. The user will still need to provide an email if it's not available on the Github public profile.
- Login to Magento thanks to Github credentials: a login Github button is displayed on the Magento traditional login page. The user click on it and he is redirected to the Github Logn page. The user provide his credentials into Github then he is redirected to your Magento website which will define to accept or not the login of the user.

## Coming soon ##

- Allow your old "customers" to login with a Github account and sync it with your website.

## Documentation ##

### Configuration ###

- Create on Github an Application. In Github, go to your account settings > Applications > Register new application > 
	- Give a name, a main url (the one of your Magento Shop), a callback url (it should be exactly the same as the one of the main url)
- In Magento Backend, go to System > Configuration > Diglin > Github. If a 403 error occur, just logout and login and go back the configuration page. Provide into the configuration, the client id and the client secret provided by Github.
- Save the configuration.
- Voilà! That's all folks!

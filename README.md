Where's My Firefighter?
===

This is a simple web application designed to give an example of how to interact with the Active911 Open API.

After activation, it simply allows the user to enter a device_id and see its last known position.


Set up
===
- Create or get the credentials for a registered 3rd party oauth application from Active911 (client_id and client_secret), and also a redirect_uri, ie "http://localhost:8081/map.php" if the container will run on port 8081
- Enter the credentials as well redirect_uri into the config.sample.php and save it as config.php
- In the base directory of the repo, build the Dockerfile into an image, ie "docker built -t wmf ."
- Run a container with the built image, making sure to forward the same port you specified in the config.php, ie "docker run -d -p 8081:80 wmf" to forward port 8081 to the container's port 80

Usage
===

- Browse to map.php of your container, ie "http://localhost:8081/map.php" if you used port 8081
- This should redirect you to the Active911 OAuth workflow, which will generate an access token and store it in your cookies for "localhost"
- After the token is generated, it should redirect you to "http://localhost:8081/map.php".  The map will start centered on the location of your agency, and allow you to enter the device id of any device in that agency.

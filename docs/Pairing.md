# Connector pairing with Apple Home

The Connector in the FastyBird system can either run automatically or be executed manually by triggering the execute command.

```shell
php bin/fb-console fb:homekit-connector:execute
```

> [!NOTE]
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

You have to select which connector should be started:

```
HomeKit connector - service
===========================

 ! [NOTE] This action will run connector service

 Would you like to continue? (yes/no) [no]:
 > y

 Please select connector to execute:
  [0] my-homekit-server [My HomeKit server]
 > my-homekit-server [My HomeKit server]
```

When the connector is successfully started, you will receive instructions on how to connect the connector with Apple Home.

```
 ! [NOTE] Setup payload: X-HM://00244F09T7b37


 [INFO] Scan this code with your HomeKit app on your iOS device:



  █▀▀▀▀▀█   █▀▄▄█ █ ▀▄█ █▀▀▀▀▀█  
  █ ███ █   ▄▄▄▄▀▀ ▄█▄▀ █ ███ █  
  █ ▀▀▀ █ ▀▀▄ ▀██▀█▀ ▄█ █ ▀▀▀ █  
  ▀▀▀▀▀▀▀ █▄▀▄█▄▀ █▄█▄█ ▀▀▀▀▀▀▀  
    ▀█▄▄▀▀▀▄▄▀▄▀▄▄ ▄ ▀ ▀█▄█▄ ▄   
  ▄ ▄██▀▀▄▀▀▄  █▀ █▀ ▄▀▄  ▀ ▀█▄  
  ▄ █▄  ▀ █ ▀▀▄▄▄█ ▄▀ ▄▀█▀█▄█▀   
  ██▄██▄▀▄▄  ▄ █▄▄▄ ██▀██▄▄ ▄▀█  
  ▀▀▀▄▄▀▀▀▀ █ ▀▄██▀▄▀ ▄  █ ▄▀ ▄  
  ▀ ▀█▄▀▀ ▀█▀███  ▄██▄█▀▄▀▀▀█ ▄  
   ▀ ▀▀ ▀▀▄▀▀▀▄▀ ██▀███▀▀▀█▀ ▄   
  █▀▀▀▀▀█ ▀▀ ▄ ██▄█▀▄▀█ ▀ █ ▄█   
  █ ███ █ ▄▀▄▀   █ ▀ ▀▀███▀▀▄█   
  █ ▀▀▀ █ ▀ ▀▄█▀ ██▄▄█▄█  █ █ █  
  ▀▀▀▀▀▀▀  ▀▀▀ ▀▀  ▀▀ ▀▀▀  ▀▀▀   



 [INFO] Or enter this code in your HomeKit app on your iOS device: 394-45-281.
```

- Open the Home <img alt="home" height="16.42px" src="https://github.com/FastyBird/homekit-connector/blob/main/docs/_media/home_icon.png" /> app on your device.
- Tap the Home tab, then tap <img alt="plus" height="16.42px" src="https://github.com/FastyBird/homekit-connector/blob/main/docs/_media/plus_icon.png" />.
- Tap **Add Accessory**, then scan the QR code shown in the console.

After the Apple Home and FastyBird HomeKit connector establish communication, they will exchange information.
If the exchange is successful, you will receive a confirmation on your Apple device. Follow the steps displayed on
your Apple device to proceed.

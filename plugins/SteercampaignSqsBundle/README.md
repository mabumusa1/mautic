# SteerCampaing.com SQS Spool

This Mautic plugin changes the default beahvior Swiftmail by spooling the mail files in AWS SQS queues instead of storing email files.

# Installation
  - Upload the files in this repo to Mautic plugins/SteercampaignSqsBundle
  - Remove cache ```sudo rm -rf app/cache/*```
  - Go to mautic settings > plugins > click Install / Upgrade Plugin
  - Add a new cron job ```php bin/console steercampaign:sqs:send```
 
# Configuration
 - Click on the newly installed bundle, the configuration screen will show
 - Fill the AWS Keys and
 - Test the configuration
 - Publish
 - SQS will be come the place where spool files are hosted

![SQS Configuration](https://user-images.githubusercontent.com/12627658/88897842-9cebb400-d254-11ea-8a94-eb8f1777d468.png)

## Author

Mohammad Abu Musa
m.abumusa@gmail.com
https://steercampaign.com
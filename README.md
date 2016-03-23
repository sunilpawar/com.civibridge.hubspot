com.civibridge.hubspot
==============================

For each Hubspot list that you want to integrate, you set up a CiviCRM group.
This will control the subscribers in that list. Add contacts to the group in
CiviCRM and after pushing the sync button, those will be subscribed to your Hubspot
list. If you remove contacts from the group in CiviCRM, the sync will unsubscribe
them at Hubspot. If anyone clicks an unsubscribe link in a Hubspot email,
they are automatically removed from your CiviCRM group.

## How to Install

1. Download extension from https://github.com/sunilpawar/com.civibridge.hubspot
2. Unzip / untar the package and place it in your configured extensions directory.
3. When you reload the Manage Extensions page the new “Hubspot” extension should be listed with an Install link.
4. Proceed with install.

Before the extension can be used you must set up your API keys...

To get your accounts API you should follow these instructions http://knowledge.hubspot.com/articles/kcs_article/integrations/how-do-i-get-my-hubspot-api-key

Once you’ve setup your Hubspot API key it can be added to CiviCRM through "Mailings >> Hubspot Settings" screen, with url https://<<your_site>>/civicrm/hubspot/settings?reset=1.
## Basic Use

In Hubspot: Set up an empty list, lets call it Newsletter.
In CiviCRM: you need a group to track subscribers to your Hubspot Newsletter
List. You can create a new blank group, or choose an existing group (or smart
group). The CiviCRM Group's settings page has an additional fieldset called
Hubspot.

Save your group's settings.

The next step is to get CiviCRM and Hubspot in sync. **Which way you do this
is important**. In our example we have assumed a new, blank Hubspot list and
a populated CiviCRM Group. So we want to do a **CiviCRM to Hubspot** Sync.
However, if we had set up an empty group in CiviCRM for a pre-existing
Hubspot list, we would want to do a **Hubspot to CiviCRM** sync.

So for our example, with an empty Hubspot list and a CiviCRM newsletter group
with contacts in, you'll find the **CiviCRM to Hubspot Sync** function in the
**Mailings** menu.

Push the Sync button and after a while (for a large
list/group) you should see a summary screen.

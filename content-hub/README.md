# ZeroBooze content hub

Deploy this folder alongside the existing design folders using the existing Cloudways Deployment via Git connection.

After pulling the commit in Cloudways, connect through SSH, go to the application public_html directory, and run:

```sh
php content-hub/setup-cli.php
```

The installer asks for the existing application database credentials and a new review dashboard password. It creates only two new tables with the zb_hub_ prefix and saves configuration outside public_html in private_html/zerobooze-content-hub.php. Never commit that configuration or credentials to GitHub. The installer refuses to replace an existing configuration and cannot run through the web.

Public hub: /content-hub/
Protected feedback dashboard: /content-hub/admin.php

Reader notes and questionnaire answers stay as browser drafts until the reviewer explicitly sends them. Sent feedback is stored in MySQL. Reviewer names are self-reported. The dashboard supports status changes, search, and CSV export. Submitted feedback is visible only after dashboard sign-in.

Verify after setup: submit a test comment, submit questionnaire answers, sign in to the dashboard, confirm both records, change status, and download CSV. Confirm that an unsigned visitor cannot list or export records. PHP syntax has been checked with a parser; server runtime and database integration require verification on the PHP host.

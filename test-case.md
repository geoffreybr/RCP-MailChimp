# Test case for updating MailChimp user using API

Because of MailChimp (MC) API specifications, it is not possible to subscribe back a user that
unsubscribes through the API.

| MC User status                       | Never subscribed | Pending                       | Subscribed     | Unsubscribed / Deleted        | Cleaned |
| ------------------------------------:|:----------------:|:-----------------------------:|:--------------:|:-----------------------------:|:-------:|
| Subscribe                            | ✅ Pending        | ✅ Impossible                  | /              | ✅ Impossible                  | ?       |
| Unsubscribe                          | /                | ✅ Impossible                  | ✅ Unsubscribed | /                             | ?       |
| Modify email address                 | /                | ❌ The MC user is not modified | ✅ Subscribed   | ❌ The MC user is not modified | ?       |
| Modify email address & Unsubscribe   | /                | ✅ Impossible                  | ✅ Unsubscribed | /                             | ?       |
| Modify email address & Subscribe     | ✅ Pending        | ✅ Impossible                  | /              | ✅ Pending                     | ?       |

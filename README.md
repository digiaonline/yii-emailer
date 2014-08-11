yii-emailer
===========

Extension for creating and sending emails for the Yii PHP framework.


Usage
===========

- Migrate the email_message database table by this command:
 
 ```yiic migrate --migrationPath=vendor.nordsoftware.yii-emailer.migrations```

- Attach the `EmailBehavior` to your controller or to any component that you want to use this extention on it:
```
public function behaviors()
    {
        return array_merge(parent::behaviors(), array(
            'emailer' => array(
                'class' => 'EmailBehavior',
            ),
        ));
    }
```

- Add `Emailer` component to your app config:
```
return array(
'components' => array(
        'email' => array(
            'class' => 'vendor.nordsoftware.yii-emailer.components.Emailer'
        )
    ));
```

- Now you can send the message by calling:
```
$email = $this->createEmail($from, $to, $subject, $body, array('body'=>$message));
$this->sendEmail($email);
```

- To use templates you need to define them in your app config:
```
    'templates'=>array(
      'foo'=>array(
       'subject'=>'Foo',
       'view'=>'foo', // refers to a view in views/email
      ),
    ),
```

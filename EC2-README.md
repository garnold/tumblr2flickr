    sudo yum --assumeyes update
    sudo yum --assumeyes install php-cli

    sudo cat <END > cat /etc/php.d/session.save_path.ini
    session.save_path = "/tmp"
    END

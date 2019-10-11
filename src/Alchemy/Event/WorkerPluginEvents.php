<?php

namespace  Alchemy\WorkerPlugin\Event;

final class WorkerPluginEvents
{
    const ASSETS_CREATE                     = 'assets.create';
    const ASSETS_CREATION_FAILURE           = 'assets.create_failure';
    const ASSETS_CREATION_RECORD_FAILURE    = 'assets.creation_record_failure';

    const STORY_CREATE_COVER                = 'story.create_cover';

    const POPULATE_INDEX                    = 'populate.index';
    const POPULATE_INDEX_FAILURE            = "populate.index_failure";

    const SUBDEFINITION_WRITE_META          = 'subdefinition.write_meta';

    const EXPORT_MAIL_FAILURE               = 'export.mail_failure';

    const WEBHOOK_DELIVER_FAILURE           = 'webhook.deliver_failure';
}

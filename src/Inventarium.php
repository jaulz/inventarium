<?php

namespace Jaulz\Inventarium;

use Illuminate\Support\Facades\DB;

class Inventarium
{
  public function getSchema()
  {
    return 'inventarium';
  }

  public function grant(string $role)
  {
    collect([
      'GRANT USAGE ON SCHEMA %1$s TO %2$s',
      'GRANT SELECT ON TABLE %1$s.definitions TO %2$s',
      'GRANT SELECT ON TABLE %1$s.searchables TO %2$s'
    ])->each(fn (string $statement) => DB::statement(sprintf($statement, Inventarium::getSchema(), $role)));
  }

  public function ungrant(string $role)
  {
  }
}

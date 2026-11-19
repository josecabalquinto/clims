# Track defective units separately from available stock

An equipment batch can contain both serviceable and defective units, so equipment and individual return records include a defective quantity. Returning a defective unit reduces the outstanding borrowed quantity without increasing available stock; total quantity equals available quantity plus outstanding borrowed quantity plus on-hand defective quantity. This extends the original schema to preserve correct availability without requiring an identity for every unit in a batch.

<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Videos_model extends CI_Model {
  public function list_subscriptions()
  {
    $this->db->select('username, display, thumbnail, checked, new, later');
    $this->db->join('subscription', 'username=channel');
    $this->db->where('user', $this->session->userdata('username'));
    return $this->db->get('channel');
  }

  public function list_new_subscriptions()
  {
    $this->db->select('username, display, thumbnail, checked, new, later');
    $this->db->join('subscription', 'username=channel');
    $this->db->where('user', $this->session->userdata('username'));
    $this->db->where('new >', '0');
    return $this->db->get('channel');
  }

  public function list_later_subscriptions()
  {
    $this->db->select('username, display, thumbnail, checked, new, later');
    $this->db->join('subscription', 'username=channel');
    $this->db->where('user', $this->session->userdata('username'));
    $this->db->where('later >', '0');
    return $this->db->get('channel');
  }

  // update

  public function get_subscriptions()
  {
    $this->db->select('channel');
    $this->db->where('user', $this->session->userdata('username'));
    return $this->db->get('subscription');
  }

  public function put_channel($channel)
  {
    $this->db->insert('channel', $channel);
    if ($this->db->_error_number() !== 0)
    {
      $this->db->where('username', $channel['username']);
      $this->db->update('channel', $channel);
    }
  }

  public function subscribe($channel)
  {
    $this->db->insert('subscription', array(
      'user' => $this->session->userdata('username'),
      'channel' => $channel
    ));
  }

  public function ubsubscribe($channel)
  {
    // remove subscription
    $this->db->where('user', $this->session->userdata('username'));
    $this->db->where('channel', $channel);
    $this->db->delete('subscription');
    // remove items
    $this->db->where('user', $this->session->userdata('username'));
    $this->db->where('channel', $channel);
    $this->db->delete('item');
  }

  public function cull_channels()
  {
    // get channels for which there are no subscriptions
    $results = $this->db->query('SELECT DISTINCT username FROM channel LEFT JOIN subscription ON username=channel WHERE channel IS NULL;');
    foreach ($results->result() as $row)
    {
      // remove channel
      $this->db->where('username', $row->username);
      $this->db->delete('channel');
      // remove videos
      $this->db->where('channel', $row->username);
      $this->db->delete('video');
    }
  }

  // poll

  public function get_channels()
  {
    $this->db->select('username');
    return $this->db->get('channel');
  }

  public function get_subscribers($channel)
  {
    $this->db->select('user');
    $this->db->where('channel', $channel);
    return $this->db->get('subscription');
  }

  public function put_video($video)
  {
    $this->db->insert('video', $video);
    if ($this->db->_error_number() !== 0)
    {
      $this->db->where('video', $video['video']);
      $this->db->update('video', $video);
      return FALSE;
    }
    return TRUE;
  }

  public function push_video($channel, $users, $video)
  {
    foreach ($users->result() as $user)
      $this->db->insert('item', array('video' => $video['video'], 'user' => $user->user, 'channel' => $channel));
    return $users->num_rows();
  }

  public function touch_channel($channel)
  {
    $this->db->where('username', $channel);
    $this->db->update('channel', array('checked' => time()));
  }

  public function update_new($channel)
  {
    $updates = $this->db->query('SELECT subscription.user, COUNT(*) AS count FROM subscription LEFT JOIN item ON subscription.user=item.user AND subscription.channel=item.channel WHERE subscription.channel=\'' . $channel. '\' AND item.state=0 GROUP BY subscription.user, subscription.new HAVING COUNT(*) <> subscription.new');
    foreach ($updates->result_array() as $row)
    {
      $this->db->where('user', $row['user']);
      $this->db->where('channel', $channel);
      $this->db->update('subscription', array('new' => $row['count']));
    }
  }

  public function cull_videos()
  {
    $results = $this->db->query('SELECT DISTINCT video.video FROM video LEFT JOIN item ON video.video=item.video WHERE item.video IS NULL;');
    foreach ($results->result() as $row)
    {
      $this->db->where('video', $row->video);
      $this->db->delete('video');
    }
  }
}

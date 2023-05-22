import { IconButton, Toolbar } from '@wordpress/components';
import PropTypes from 'prop-types';

import { refreshAffiliateWidgetsOnEdit } from './helpers';

const WidgetToolbar = ({
  onEdit,
}) => {
  return (
    <Toolbar>
      <>
        <IconButton
          icon="edit"
          label="Edit product carousel"
          onClick={onEdit}
        />
        <IconButton
          icon="update"
          label="Refresh display"
          onClick={refreshAffiliateWidgetsOnEdit}
        />
      </>
    </Toolbar>
  );
};

WidgetToolbar.propTypes = {
  onEdit: PropTypes.func.isRequired,
};

export default WidgetToolbar;

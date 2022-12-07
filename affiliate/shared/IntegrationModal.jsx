import './integrationModal.scss';

import { Modal } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import PropTypes from 'prop-types';

const IntegrationModal = ({
  iframeURL,
  integrationMessageType,
  onClose,
  onIntegrationSelect,
  title,
}) => {
  useEffect(() => {
    const handler = (event) => {
      const data = JSON.parse(event.data);
      if (data?.type === integrationMessageType) {
        onIntegrationSelect(data);
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, [integrationMessageType, onIntegrationSelect]);

  return (
    <Modal
      className="integration-modal changed-up0"
      onRequestClose={onClose}
      shouldCloseOnClickOutside={false}
      title={title}
      isFullScreen
    >
      <iframe
        height="100%"
        src={iframeURL}
        title={title}
        width="100%"
      />
    </Modal>
  );
};

IntegrationModal.propTypes = {
  iframeURL: PropTypes.string.isRequired,
  integrationMessageType: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
  onIntegrationSelect: PropTypes.func.isRequired,
  title: PropTypes.string.isRequired,
};

export default IntegrationModal;

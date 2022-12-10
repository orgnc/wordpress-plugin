import {
  useBlockProps,
  BlockControls,
} from '@wordpress/block-editor';
import {
  Card,
  CardBody,
  CardDivider,
  CheckboxControl,
  IconButton,
  Toolbar,
  TextControl,
} from '@wordpress/components';
import {
  createRef,
  useCallback,
  useEffect,
  useMemo,
  useState,
} from '@wordpress/element';
import { debounce } from 'lodash';
import PropTypes from 'prop-types';

import ProductCard from './ProductCard';
import ProductSearchModal from './ProductSearchModal';
import { AttributesType } from './propTypes';

const Edit = ({ attributes, setAttributes, productSearchPageUrl }) => {
  const productCardRef = createRef();
  useEffect(() => {
    if (attributes.productGuid) {
      if (productCardRef.current) {
        productCardRef.current.removeAttribute('data-organic-affiliate-processed');
      }
      window.empire?.apps?.affiliate?.init?.();
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    attributes.productGuid,
    attributes.displayImage,
    attributes.displayDescription,
    attributes.bannerText,
    attributes.description, // description override
  ]);
  const [showModal, setShowModal] = useState(!attributes.productGuid);
  const hideModal = useCallback(
    () => setShowModal(false),
    [setShowModal],
  );
  const displayModal = useCallback(
    () => setShowModal(true),
    [setShowModal],
  );

  const onProductSelect = useCallback(
    ({ product }) => {
      setAttributes({ productGuid: product.guid });
      hideModal();
    },
    [setAttributes, hideModal],
  );

  const setDisplayImage = useCallback(
    (displayImage) => setAttributes({ displayImage }),
    [setAttributes],
  );
  const setDisplayDescription = useCallback(
    (displayDescription) => setAttributes({ displayDescription }),
    [setAttributes],
  );

  const debouncedSetBannerText = useMemo(
    () => debounce((bannerText) => setAttributes({ bannerText }), 400),
    [setAttributes],
  );

  const debouncedSetDescriptionText = useMemo(
    () => debounce((description) => setAttributes({ description }), 400),
    [setAttributes],
  );

  const [bannerTextValue, setBannerTextValue] = useState(attributes.bannerText);
  const setBannerText = useCallback(
    (bannerText) => {
      setBannerTextValue(bannerText);
      debouncedSetBannerText(bannerText);
    },
    [debouncedSetBannerText],
  );

  const [descriptionValue, setDescriptionValue] = useState(attributes.description);
  const setDescriptionText = useCallback(
    (description) => {
      setDescriptionValue(description);
      debouncedSetDescriptionText(description);
    },
    [debouncedSetDescriptionText],
  );

  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps()}>
      {showModal
        && (
        <ProductSearchModal
          onClose={hideModal}
          onProductSelect={onProductSelect}
          productSearchPageUrl={productSearchPageUrl}
        />
        )}
      <BlockControls>
        <Toolbar>
          <IconButton
            icon="search"
            label="Select a product"
            onClick={displayModal}
          />
        </Toolbar>
      </BlockControls>
      <Card>
        {attributes.productGuid ? (
          <>
            <CardBody>
              <h4>Options</h4>
              <CheckboxControl
                checked={attributes.displayImage}
                help="Whether or not the product image should be displayed"
                label="Display Image"
                onChange={setDisplayImage}
              />
              <CheckboxControl
                checked={attributes.displayDescription}
                help="Whether or not the product description should be displayed"
                label="Display Description"
                onChange={setDisplayDescription}
              />
              {attributes.displayImage && (
                <TextControl
                  help="Text for product card banner/award"
                  label="Banner Text"
                  onChange={setBannerText}
                  value={bannerTextValue}
                />
              )}
              {attributes.displayDescription && (
                <TextControl
                  help="Text to display instead of the default descriptoion"
                  label="Description Text"
                  onChange={setDescriptionText}
                  value={descriptionValue}
                />
              )}
            </CardBody>
            <CardDivider />
            <CardBody>
              <ProductCard
                ref={productCardRef}
                bannerText={attributes.bannerText}
                description={attributes.description}
                displayDescription={attributes.displayDescription}
                displayImage={attributes.displayImage}
                productGuid={attributes.productGuid}
              />
            </CardBody>
          </>
        ) : (
          <CardBody>
            <h3>Product is not selected</h3>
          </CardBody>
        )}
      </Card>
    </div>
  );
};

Edit.propTypes = {
  attributes: AttributesType.isRequired,
  setAttributes: PropTypes.func.isRequired,
  productSearchPageUrl: PropTypes.string.isRequired,
};

export default Edit;

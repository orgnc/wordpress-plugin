import { useBlockProps } from '@wordpress/block-editor';

import { refreshAffiliateWidgetsOnSave } from '../../shared/helpers';
import ProductCarousel from './ProductCarousel';
import { AttributesType } from './propTypes';

const Save = ({ attributes }) => {
  refreshAffiliateWidgetsOnSave();
  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps.save()}>
      {attributes?.productCarouselSnippet && (
        <ProductCarousel
          productCarouselEditURL={attributes.productCarouselEditURL}
          productCarouselSnippet={attributes.productCarouselSnippet}
        />
      )}
    </div>
  );
};

Save.propTypes = {
  attributes: AttributesType.isRequired,
};

export default Save;
